<?php

namespace App\Livewire;

use App\Models\Message;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Chat extends Component
{
    public $content = '';
    public $recipient_type = 'user';
    public $recipient_id;
    public $users = [];
    public $subjects = [];

    public function mount()
    {
        if (Auth::user()->hasRole('student')) {
            $subjectUsers = collect();
            foreach (Auth::user()->subjects as $subject) {
                $subjectUsers = $subjectUsers->merge($subject->users);
            }

            $directorsAndAdmins = User::whereIn('role', ['director', 'admin'])->get();

            $this->users = $subjectUsers->merge($directorsAndAdmins)->unique('id')->map(function ($user) {
                return ['id' => $user->id, 'name' => $user->fullname];
            })->sortBy('name')->values()->all();

            $this->subjects = Auth::user()->subjects->map(function ($subject) {
                return ['id' => $subject->id, 'name' => $subject->name];
            })->all();
        } else {
            $this->users = User::where('id', '!=', Auth::id())->get()->map(function ($user) {
                return ['id' => $user->id, 'name' => $user->fullname];
            })->sortBy('name')->values()->all();
            $this->subjects = Subject::all()->map(function ($subject) {
                return ['id' => $subject->id, 'name' => $subject->name];
            })->all();
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
    }

    public function render()
    {
        $userId = Auth::id();

        // Get all messages where the user is the sender or a recipient
        $allMessages = Message::where('sender_id', $userId)
            ->orWhereHas('recipients', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->latest()
            ->get();

        // Group messages into conversations
        $conversations = $allMessages->groupBy(function ($message) use ($userId) {
            if ($message->subject_id) {
                return 'subject_' . $message->subject_id;
            }
            // If the user is the sender, group by the first recipient
            if ($message->sender_id == $userId) {
                return 'user_' . $message->recipients->first()->id;
            }
            // If the user is the recipient, group by the sender
            return 'user_' . $message->sender_id;
        });

        // Filter messages for the selected conversation
        $filteredMessages = collect();
        if ($this->selectedConversation) {
            $type = $this->selectedConversation['type'];
            $id = $this->selectedConversation['id'];

            $filteredMessages = $allMessages->filter(function ($message) use ($type, $id, $userId) {
                if ($type === 'user') {
                    return (!$message->subject_id) &&
                           (($message->sender_id == $userId && $message->recipients->pluck('id')->contains($id)) ||
                            ($message->sender_id == $id && $message->recipients->pluck('id')->contains($userId)));
                } else { // type is 'subject'
                    return $message->subject_id == $id;
                }
            });
        }

        return view('livewire.chat', [
            'conversations' => $conversations,
            'receivedMessages' => $filteredMessages,
        ]);
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
                        if (!User::where('id', $value)->exists()) {
                            $fail('El usuario seleccionado no existe.');
                        }
                    } elseif ($this->recipient_type === 'subject') {
                        if (!Subject::where('id', $value)->exists()) {
                            $fail('El curso seleccionado no existe.');
                        }
                    }
                },
            ],
            'recipient_type' => 'required|in:user,subject,all', // Keep validation for now
        ]);

        // Step 2: Create the message.
        $message = Message::create([
            'sender_id' => Auth::id(),
            'content'   => $validated['content'],
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
                if (!Auth::user()->hasRole('student')) {
                    $recipients = User::all();
                }
                break;
        }

        // Step 4: Attach the recipients to the message.
        if ($recipients->isNotEmpty()) {
            $message->recipients()->attach($recipients->pluck('id'));
        }

        // Step 5: Reset the form fields.
        $this->reset('content', 'recipient_type', 'recipient_id');
    }
}
