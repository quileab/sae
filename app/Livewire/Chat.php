<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.chat')]
class Chat extends Component
{
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

        // Get all messages where the user is the sender or a recipient
        // Note: This is still heavy for the conversation list, but requested optimization is for the chat view pagination.
        $allMessages = Message::where('sender_id', $userId)
            ->orWhereHas('recipients', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with(['recipients' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->latest()
            ->get();

        // Group messages into conversations
        $conversations = $allMessages->groupBy(function ($message) use ($userId) {
            if ($message->subject_id) {
                return 'subject_'.$message->subject_id;
            }
            // If the user is the sender, group by the first recipient
            if ($message->sender_id == $userId) {
                // Fallback if recipients is empty (shouldn't happen in valid chat)
                return 'user_'.($message->recipients->first()->id ?? 0);
            }

            // If the user is the recipient, group by the sender
            return 'user_'.$message->sender_id;
        });

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
            'conversations' => $conversations,
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
