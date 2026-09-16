<?php

namespace App\Livewire\Messages;

use App\Models\Enrollment;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MessageList extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $attachment;

    public $search = '';

    public $selectedSubject = null;

    public $selectedContact = null;

    public $messageContent = '';

    public $messages = [];

    public $userSubjects = [];

    public $subjectContacts = [];

    public $expandedSubjects = [];

    public $messageType = 'individual'; // individual, subject, global

    private $user;

    public $selectedTab = 'users-tab';

    public $tabs = [
        'subjects-tab' => 'Materias',
        'users-tab' => 'Contactos',
    ];

    public function mount()
    {
        if (Auth::user()->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            $this->tabs = [
                'subjects-tab' => 'Materias',
                'users-tab' => 'Contactos',
                'global-tab' => 'Global',
            ];
        }
        $this->loadUserSubjects();
    }

    public function loadUserSubjects()
    {
        $this->userSubjects = Auth::user()->subjects()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })->get();
        // Check If subjects array is empty, load global messages
        if ($this->userSubjects->isEmpty()) {
            $this->messageType = 'global';
        } else {
            $this->messageType = 'subject';
            $this->selectedSubject = $this->userSubjects->first();
            $this->loadSubjectContacts($this->selectedSubject->id);
        }
    }

    public function loadSubjectContacts($subjectId)
    {
        $this->subjectContacts = $this->selectedSubject->subjectUsers($subjectId)
            ->with('user')
            ->when($this->search, function ($query) {
                $query->whereHas('user', function ($query) {
                    $query->where('firstname', 'like', '%'.$this->search.'%')
                        ->orWhere('lastname', 'like', '%'.$this->search.'%');
                });
            })
            ->get();
    }

    public function updatedSearch()
    {
        switch ($this->selectedTab) {
            case 'subjects-tab':
                $this->loadUserSubjects();
                break;
            case 'users-tab':
                $this->loadSubjectContacts($this->selectedSubject->id);
                break;
            case 'global-tab':
                break;
        }
    }

    public function updatedSelectedTab()
    {
        $this->search = '';
        if ($this->selectedTab === 'global-tab') {
            $this->messageType = 'global';
        }
    }

    public function updatedSelectedSubject()
    {
        $this->loadSubjectContacts();
    }

    public function selectSubject(Subject $subjectId)
    {
        $this->selectedSubject = $subjectId;
        $this->loadSubjectContacts($subjectId->id);
        $this->messageType = 'subject';
        $this->getMessages();
    }

    public function selectContact($contactId)
    {
        $this->selectedContact = $contactId;
        $this->messageType = 'individual';
        $this->getMessages();
    }

    public function sendMessage()
    {
        $user = Auth::user();

        if ($this->messageType === 'global' && ! $user->isStaff()) {
            abort(403, 'No tienes permiso para enviar mensajes globales.');
        }

        if ($this->messageType === 'subject') {
            $subject = $this->selectedSubject;
            abort_if(! $subject, 404);
            abort_if(! $this->canChatWithSubject($user, $subject), 403, 'No tienes permiso para enviar mensajes a esta materia.');
        }

        if ($this->messageType === 'individual') {
            $recipient = User::find($this->selectedContact);
            abort_if(! $recipient, 404);
            abort_if(! $this->canChatWithUser($user, $recipient), 403, 'No tienes permiso para enviar mensajes a este contacto.');
        }

        $message = new Message;
        $message->sender_id = Auth::id();
        $message->content = $this->messageContent;
        $message->messageType = $this->messageType;
        $message->subject_id = $this->selectedSubject?->id;

        if ($this->attachment) {
            $path = $this->attachment->store('attachments', 'public');
            $message->attachment_path = $path;
        }

        switch ($this->messageType) {
            case 'individual':
                if ($this->selectedContact) {
                    $message->receiver_id = $this->selectedContact;
                    $message->subject_id = null;
                    $message->save();
                    $this->dispatch('messageSent');
                }
                break;
            case 'subject':
                $message->receiver_id = null;
                $message->save();
                $subjectUsers = $this->selectedSubject->users;
                foreach ($subjectUsers as $user) {
                    MessageRead::create([
                        'message_id' => $message->id,
                        'user_id' => $user->id,
                        'read_at' => null,
                    ]);
                }
                $this->dispatch('messageSent');
                break;
            case 'global':
                $message->receiver_id = null;
                $message->subject_id = null;
                $message->save();
                $allUsers = User::all();
                foreach ($allUsers as $user) {
                    MessageRead::create([
                        'message_id' => $message->id,
                        'user_id' => $user->id,
                        'read_at' => null,
                    ]);
                }
                $this->dispatch('messageSent');
                break;
        }

        $this->reset('messageContent', 'attachment');
        $this->dispatch('messageSent');
        $this->dispatch('scroll-to-bottom');
    }

    #[On('messageSent')]
    public function refreshMessages()
    {
        $this->resetPage();
        $this->getMessages();
    }

    private function canChatWithUser(User $user, User $recipient): bool
    {
        if ($user->id === $recipient->id) {
            return false;
        }

        if ($user->isStaff()) {
            return true;
        }

        if ($recipient->isStaff()) {
            return true;
        }

        if ($user->hasRole('student') && $recipient->hasRole('student')) {
            return false;
        }

        return Enrollment::where('user_id', $user->id)
            ->whereIn('subject_id', function ($query) use ($recipient) {
                $query->select('subject_id')
                    ->from('enrollments')
                    ->where('user_id', $recipient->id);
            })->exists();
    }

    private function canChatWithSubject(User $user, Subject $subject): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return $user->hasSubject($subject->id);
    }

    public function getMessages()
    {
        $query = Message::query()
            ->where('sender_id', Auth::id());

        if ($this->messageType === 'individual' && $this->selectedContact) {
            $query->orWhere(function ($q) {
                $q->where('sender_id', $this->selectedContact)
                    ->where('receiver_id', Auth::id());
            });
            $query->where('messageType', 'individual');
        } elseif ($this->messageType === 'subject' && $this->selectedSubject) {
            $query->where('subject_id', $this->selectedSubject->id)
                ->where('messageType', 'subject');
        } elseif ($this->messageType === 'global') {
            $query->where('messageType', 'global');
        }

        return $query->latest()->paginate(10);
    }

    public function render()
    {
        return view('livewire.messages.message-list');
    }
}
