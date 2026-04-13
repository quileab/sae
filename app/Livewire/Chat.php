<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.chat')]
class Chat extends Component
{
    #[Url]
    public $user_id;

    public $content = '';

    public $recipient_type = 'user';

    public $recipient_id;

    public $users = [];

    public $subjects = [];

    public $selectedSubjectId;

    public $careers = [];

    public $selectedCareerId;

    public $allSubjects = [];

    public $amount = 20;

    public $activeTab = 'messages';

    private function getRoleEmoji(string $role): string
    {
        return match ($role) {
            'student' => '🧑‍🎓',
            'teacher' => '🧑‍🏫',
            'admin' => '👑',
            'director' => '⭐',
            default => '👤',
        };
    }

    public function loadMore()
    {
        $this->amount += 20;
        $this->dispatch('messages-loaded');
    }

    public function mount()
    {
        $user = Auth::user();

        $subjects = $user->subjects()->with('career')->get();

        $this->careers = $subjects->pluck('career')->unique('id')->filter()->map(function ($career) {
            return ['id' => $career->id, 'name' => $career->name];
        })->sortBy('name')->values()->all();

        $this->allSubjects = $subjects->map(function ($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'career_id' => $subject->career_id,
            ];
        })->all();

        $this->subjects = $this->allSubjects;

        if ($user->hasRole('student')) {
            $teacherIds = collect();
            foreach ($user->subjects as $subject) {
                $teacherIds = $teacherIds->merge($subject->users()->where('role', 'teacher')->pluck('users.id'));
            }

            $directorAndAdminIds = User::whereIn('role', ['director', 'admin'])->pluck('id');

            $allIds = $teacherIds->merge($directorAndAdminIds)->unique();

            $this->users = User::whereIn('id', $allIds)->get()->map(function ($user) {
                return ['id' => $user->id, 'name' => $this->getRoleEmoji($user->role).' '.$user->fullname];
            })->sortBy('name')->values()->all();
        } else {
            // For teachers and admins, users are loaded on subject selection.
            $this->users = [];
        }

        // Auto-select conversation if user_id is provided in URL
        if ($this->user_id) {
            $this->selectConversation('user', $this->user_id);
            $this->activeTab = 'messages';
        }
    }

    public function updatedSelectedCareerId($careerId)
    {
        if ($careerId) {
            $this->subjects = collect($this->allSubjects)->where('career_id', $careerId)->values()->all();
        } else {
            $this->subjects = $this->allSubjects;
        }
        $this->selectedSubjectId = null;
        if (! Auth::user()->hasRole('student')) {
            $this->users = [];
        }
    }

    public function updatedSelectedSubjectId($subjectId)
    {
        if (! Auth::user()->hasRole('student')) {
            if ($subjectId) {
                $subject = Subject::find($subjectId);
                if ($subject) {
                    $this->users = $subject->users()->where('users.id', '!=', Auth::id())->get()->map(function ($user) {
                        return ['id' => $user->id, 'name' => $this->getRoleEmoji($user->role).' '.$user->fullname];
                    })->sortBy('name')->values()->all();
                }
            } else {
                $this->users = [];
            }
        }
    }

    public $selectedConversation = null;

    public function selectConversation($type, $id)
    {
        $this->selectedConversation = [
            'type' => $type,
            'id' => $id,
        ];

        $this->recipient_type = $type;
        $this->recipient_id = $id;
        $this->amount = 20; // Reset pagination

        // Mark messages as read
        Auth::user()->receivedMessages()
            ->where(function ($query) use ($type, $id) {
                if ($type === 'user') {
                    $query->where('sender_id', $id)->whereNull('subject_id');
                } else { // type is 'subject'
                    $query->where('subject_id', $id);
                }
            })
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->dispatch('scroll-to-bottom');
    }

    public function render()
    {
        $userId = Auth::id();

        // Optimización avanzada: Obtener el ID del último mensaje de cada conversación
        // Esto reduce drásticamente el uso de memoria al no cargar miles de mensajes.
        $subquery = Message::selectRaw('MAX(id) as id')
            ->where('sender_id', $userId)
            ->orWhereHas('recipients', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->groupBy(\DB::raw('COALESCE(subject_id, IF(sender_id = '.$userId.', (SELECT user_id FROM message_user WHERE message_id = messages.id LIMIT 1), sender_id))'));

        $recentMessages = Message::whereIn('id', $subquery)
            ->with(['sender', 'recipients', 'subject.career'])
            ->latest()
            ->get();

        $conversations = [];
        $processedKeys = [];

        foreach ($recentMessages as $message) {
            $key = '';
            $label = '';
            $subLabel = '';
            $id = 0;
            $type = '';

            if ($message->subject_id) {
                $type = 'subject';
                $id = $message->subject_id;
                $key = 'subject_'.$id;
                $label = $message->subject->name ?? 'Curso';
                $subLabel = $message->subject->career->name ?? '';
            } else {
                $type = 'user';
                if ($message->sender_id == $userId) {
                    $recipient = $message->recipients->where('id', '!=', $userId)->first();
                    $id = $recipient->id ?? 0;
                    $label = $recipient->fullname ?? 'Usuario';
                } else {
                    $id = $message->sender_id;
                    $label = $message->sender->fullname ?? 'Usuario';
                }
                $key = 'user_'.$id;
            }

            if (!in_array($key, $processedKeys)) {
                $unreadCount = 0;
                if ($message->sender_id !== $userId) {
                    $myPivot = $message->recipients->where('id', $userId)->first()?->pivot;
                    if ($myPivot && is_null($myPivot->read_at)) {
                        $unreadCount = 1; 
                    }
                }

                $conversations[] = [
                    'key' => $key,
                    'type' => $type,
                    'id' => $id,
                    'label' => $label,
                    'subLabel' => $subLabel,
                    'last_date' => $message->created_at,
                    'unread' => $unreadCount > 0
                ];
                $processedKeys[] = $key;
            }
        }

        // Filter messages for the selected conversation
        $filteredMessages = collect();
        if ($this->selectedConversation) {
            $type = $this->selectedConversation['type'];
            $id = $this->selectedConversation['id'];

            $query = Message::query();

            if ($type === 'user') {
                $query->where(function ($q) use ($id, $userId) {
                    $q->whereNull('subject_id')
                        ->where(function ($subQ) use ($id, $userId) {
                            $subQ->where(function ($q2) use ($id, $userId) {
                                $q2->where('sender_id', $userId)
                                    ->whereHas('recipients', function ($r) use ($id) {
                                        $r->where('user_id', $id);
                                    });
                            })->orWhere(function ($q2) use ($id, $userId) {
                                $q2->where('sender_id', $id)
                                    ->whereHas('recipients', function ($r) use ($userId) {
                                        $r->where('user_id', $userId);
                                    });
                            });
                        });
                });
            } else {
                $query->where('subject_id', $id);
            }

            $filteredMessages = $query->latest()
                ->with(['subject.career', 'sender'])
                ->take($this->amount)
                ->get();
        }

        return view('livewire.chat', [
            'conversationList' => $conversations,
            'receivedMessages' => $filteredMessages,
        ])->layout('layouts.chat');
    }

    public function sendMessage()
    {
        // Step 1: Validate the basic input.
        $validated = $this->validate([
            'content' => 'required|string',
            'recipient_id' => [
                'required_if:recipient_type,user,subject',
                function ($attribute, $value, $fail) {
                    if ($this->recipient_type === 'user') {
                        if (! User::where('id', $value)->exists()) {
                            $fail('El usuario seleccionado no existe.');
                        }
                    } elseif ($this->recipient_type === 'subject') {
                        if (! Subject::where('id', $value)->exists()) {
                            $fail('El curso seleccionado no existe.');
                        }
                    }
                },
            ],
            'recipient_type' => 'required|in:user,subject,all', // Keep validation for now
        ]);

        // Prevent students from sending messages to other students
        if (Auth::user()->hasRole('student') && $validated['recipient_type'] === 'user') {
            $recipient = User::find($validated['recipient_id']);
            if ($recipient && $recipient->hasRole('student')) {
                session()->flash('error', 'No puedes enviar mensajes a otros estudiantes.');

                return;
            }
        }

        // Step 2: Create the message.
        $message = Message::create([
            'sender_id' => Auth::id(),
            'content' => $validated['content'],
            'subject_id' => $validated['recipient_type'] === 'subject' ? $validated['recipient_id'] : null,
        ]);

        // Step 3: Determine recipients based on the type.
        $recipients = collect();
        switch ($validated['recipient_type']) {
            case 'user':
                $recipients = User::where('id', $validated['recipient_id'])->get();
                break;
            case 'subject':
                $subject = Subject::find($validated['recipient_id']);
                if ($subject) {
                    $recipients = $subject->users;
                }
                break;
            case 'all':
                // For safety, only non-students can send to all.
                if (! Auth::user()->hasRole('student')) {
                    $recipients = User::select('id')->get();
                }
                break;
        }

        // Step 4: Attach the recipients to the message.
        if ($recipients->isNotEmpty()) {
            $message->recipients()->attach($recipients->pluck('id'));
        }

        // Step 5: Reset the form fields.
        $this->reset('content');
        // Don't reset recipient info so user can continue chatting

        $this->dispatch('scroll-to-bottom');
    }
}
