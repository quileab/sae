<?php

namespace App\Livewire;

use App\Models\Career;
use App\Models\Enrollment;
use App\Models\Message;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.chat')]
class Chat extends Component
{
    #[Url]
    public $user_id;

    #[Url]
    public $subject_id;

    public $content = '';

    /** @var string 'user'|'subject'|'career'|'teachers' */
    public $recipient_type = 'user';

    public $recipient_id;

    /** @var array<int, array{id: int, name: string}> */
    public $users = [];

    /** @var array<int, array{id: int, name: string}> */
    public $staffUsers = [];

    /** @var array<int, array{id: int, name: string, career_id: int}> */
    public $subjects = [];

    public $selectedSubjectId;

    /** @var array<int, array{id: int, name: string}> */
    public $careers = [];

    public $selectedCareerId;

    /** @var array<int, array{id: int, name: string, career_id: int}> */
    public $allSubjects = [];

    public $amount = 20;

    public $activeTab = 'messages';

    /** @var array{type: string, id: int}|null */
    public $selectedConversation = null;

    private function isStaff(): bool
    {
        return Auth::user()->hasAnyRole(['admin', 'director', 'administrative', 'treasurer', 'preceptor']);
    }

    private function getRoleEmoji(string $role): string
    {
        return match ($role) {
            'student' => '🧑‍🎓',
            'teacher' => '🧑‍🏫',
            'admin' => '👑',
            'director' => '⭐',
            'administrative' => '🏢',
            'preceptor' => '📋',
            'treasurer' => '💰',
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

        if ($this->isStaff()) {
            // El personal administrativo/directivo tiene acceso a todas las carreras y materias.
            $allCareers = Career::orderBy('name')->get();

            $this->careers = $allCareers->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->all();

            $allSubjectsQuery = Subject::with('career')->orderBy('name')->get();

            $this->allSubjects = $allSubjectsQuery->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'career_id' => $s->career_id,
            ])->all();

            $this->subjects = $this->allSubjects;
            $this->users = [];
        } elseif ($user->hasRole('teacher')) {
            // Los docentes ven sus materias asignadas.
            $subjects = $user->subjects()->with('career')->get();

            $this->careers = $subjects->pluck('career')->unique('id')->filter()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ])->sortBy('name')->values()->all();

            $this->allSubjects = $subjects->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'career_id' => $s->career_id,
            ])->all();

            $this->subjects = $this->allSubjects;

            // Estudiantes de sus materias se cargarán al seleccionar curso.
            $this->users = [];

            // Personal / Staff para el docente.
            $this->staffUsers = User::all()
                ->filter(fn ($u) => $u->isStaff())
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $this->getRoleEmoji($u->role).' '.$u->fullname,
                ])
                ->sortBy('name')
                ->values()
                ->all();
        } elseif ($user->hasRole('student')) {
            // Los estudiantes ven sus materias y sus docentes + personal administrativo/directivo.
            $subjects = $user->subjects()->with('career')->get();

            $this->careers = [];
            $this->allSubjects = $subjects->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'career_id' => $s->career_id,
            ])->all();
            $this->subjects = $this->allSubjects;

            // Solo docentes de sus materias.
            $teacherIds = collect();
            foreach ($subjects as $subject) {
                $teacherIds = $teacherIds->merge(
                    $subject->users()->where('role', 'teacher')->pluck('users.id')
                );
            }

            $this->users = User::whereIn('id', $teacherIds->unique())
                ->get()
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $this->getRoleEmoji($u->role).' '.$u->fullname,
                ])
                ->sortBy('name')
                ->values()
                ->all();

            // Personal / Staff para el estudiante.
            $this->staffUsers = User::all()
                ->filter(fn ($u) => $u->isStaff())
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $this->getRoleEmoji($u->role).' '.$u->fullname,
                ])
                ->sortBy('name')
                ->values()
                ->all();
        }

        // Auto-seleccionar conversación desde URL.
        if ($this->user_id) {
            $this->selectConversation('user', $this->user_id);
            $this->activeTab = 'messages';
        }

        if ($this->subject_id) {
            $this->selectConversation('subject', $this->subject_id);
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
        $user = Auth::user();

        if ($user->hasRole('student')) {
            return;
        }

        if ($subjectId) {
            $subject = Subject::find($subjectId);
            if ($subject) {
                if ($user->hasRole('teacher')) {
                    // El docente solo puede ver a los estudiantes del curso.
                    $this->users = $subject->users()
                        ->where('role', 'student')
                        ->where('users.id', '!=', Auth::id())
                        ->get()
                        ->map(fn ($u) => [
                            'id' => $u->id,
                            'name' => $this->getRoleEmoji($u->role).' '.$u->fullname,
                        ])
                        ->sortBy('name')
                        ->values()
                        ->all();
                } else {
                    // El personal ve a todos los usuarios del curso.
                    $this->users = $subject->users()
                        ->where('users.id', '!=', Auth::id())
                        ->get()
                        ->map(fn ($u) => [
                            'id' => $u->id,
                            'name' => $this->getRoleEmoji($u->role).' '.$u->fullname,
                        ])
                        ->sortBy('name')
                        ->values()
                        ->all();
                }
            }
        } else {
            $this->users = [];
        }
    }

    public function updatedRecipientType($type)
    {
        // Al cambiar el tipo de destinatario, limpiar la selección previa.
        $this->recipient_id = null;

        if (! in_array($type, ['user', 'staff'])) {
            $this->selectedSubjectId = null;
            if (! Auth::user()->hasRole('student')) {
                $this->users = [];
            }
        }

        // Si el docente selecciona "Estudiante" (user), preseleccionar la primera carrera disponible.
        if ($type === 'user' && Auth::user()->hasRole('teacher') && ! empty($this->careers)) {
            $this->selectedCareerId = $this->careers[0]['id'];
            $this->updatedSelectedCareerId($this->selectedCareerId);
        }
    }

    public function selectConversation($type, $id)
    {
        if ($type === 'user') {
            $recipient = User::find($id);
            if (! $recipient || ! $this->canChatWithUser(Auth::user(), $recipient)) {
                session()->flash('error', 'No tienes permiso para iniciar una conversación con este usuario.');

                return;
            }
        } elseif ($type === 'subject') {
            $subject = Subject::find($id);
            if (! $subject || ! $this->canChatWithSubject(Auth::user(), $subject)) {
                session()->flash('error', 'No tienes permiso para iniciar una conversación en este curso.');

                return;
            }
        } elseif ($type === 'career') {
            if (! $this->isStaff()) {
                session()->flash('error', 'No tienes permiso para enviar mensajes a una carrera.');

                return;
            }
        }

        $this->selectedConversation = [
            'type' => $type,
            'id' => $id,
        ];

        $this->recipient_type = $type;
        $this->recipient_id = $id;
        $this->amount = 20;

        if (in_array($type, ['user', 'subject'])) {
            // Marcar mensajes como leídos.
            Auth::user()->receivedMessages()
                ->where(function ($query) use ($type, $id) {
                    if ($type === 'user') {
                        $query->where('sender_id', $id)->whereNull('subject_id');
                    } else {
                        $query->where('subject_id', $id);
                    }
                })
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        $this->dispatch('scroll-to-bottom');
    }

    public function render()
    {
        $userId = Auth::id();

        $subquery = Message::selectRaw('MAX(id) as id')
            ->where('sender_id', $userId)
            ->orWhereHas('recipients', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->groupBy(DB::raw('COALESCE(subject_id, IF(sender_id = '.$userId.', (SELECT user_id FROM message_user WHERE message_id = messages.id LIMIT 1), sender_id))'));

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

            if (! in_array($key, $processedKeys)) {
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
                    'unread' => $unreadCount > 0,
                ];
                $processedKeys[] = $key;
            }
        }

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

    private function canChatWithUser(User $user, User $recipient): bool
    {
        if ($user->id === $recipient->id) {
            return false;
        }

        // El personal siempre puede chatear con cualquiera.
        if ($this->isStaff()) {
            return true;
        }

        // Cualquiera puede chatear con el personal.
        if ($recipient->isStaff()) {
            return true;
        }

        // Los estudiantes NO pueden chatear entre sí.
        if ($user->hasRole('student') && $recipient->hasRole('student')) {
            return false;
        }

        // Docente ↔ Docente: permitido si comparten materia.
        // Docente ↔ Estudiante: permitido si comparten materia.
        return Enrollment::where('user_id', $user->id)
            ->whereIn('subject_id', function ($query) use ($recipient) {
                $query->select('subject_id')
                    ->from('enrollments')
                    ->where('user_id', $recipient->id);
            })->exists();
    }

    private function canChatWithSubject(User $user, Subject $subject): bool
    {
        if ($this->isStaff()) {
            return true;
        }

        return $user->hasSubject($subject->id);
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function getRecipientTypeOptions(): array
    {
        $user = Auth::user();

        if ($user->hasRole('student')) {
            return [
                ['id' => 'staff', 'name' => 'Personal / Staff'],
                ['id' => 'user', 'name' => 'Docentes'],
                ['id' => 'subject', 'name' => 'Curso (mi materia)'],
            ];
        }

        if ($user->hasRole('teacher')) {
            return [
                ['id' => 'staff', 'name' => 'Personal / Staff'],
                ['id' => 'user', 'name' => 'Estudiante'],
                ['id' => 'subject', 'name' => 'Curso completo'],
            ];
        }

        // Staff: opciones completas.
        return [
            ['id' => 'user', 'name' => 'Usuario específico'],
            ['id' => 'subject', 'name' => 'Curso completo'],
            ['id' => 'career', 'name' => 'Carrera completa'],
            ['id' => 'teachers', 'name' => 'Todos los docentes'],
            ['id' => 'all', 'name' => 'Todos los usuarios'],
        ];
    }

    public function sendMessage()
    {
        $user = Auth::user();

        $validTypes = $user->hasRole('student')
            ? 'in:user,subject,staff'
            : ($user->hasRole('teacher')
                ? 'in:user,subject,staff'
                : 'in:user,subject,career,teachers,all');

        $validated = $this->validate([
            'content' => 'required|string',
            'recipient_type' => ['required', $validTypes],
            'recipient_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    $type = $this->recipient_type;

                    if (in_array($type, ['all', 'teachers'])) {
                        return; // Sin ID específica para estos tipos.
                    }

                    if (! $value) {
                        $fail('Debes seleccionar un destinatario.');

                        return;
                    }

                    if ($type === 'user' || $type === 'staff') {
                        $recipient = User::find($value);
                        if (! $recipient) {
                            $fail('El usuario seleccionado no existe.');
                        } elseif (! $this->canChatWithUser(Auth::user(), $recipient)) {
                            $fail('No tienes permiso para chatear con este usuario.');
                        }
                    } elseif ($type === 'subject') {
                        $subject = Subject::find($value);
                        if (! $subject) {
                            $fail('El curso seleccionado no existe.');
                        } elseif (! $this->canChatWithSubject(Auth::user(), $subject)) {
                            $fail('No tienes permiso para chatear en este curso.');
                        }
                    } elseif ($type === 'career') {
                        if (! $this->isStaff()) {
                            $fail('No tienes permiso para enviar mensajes a una carrera.');
                        } elseif (! Career::find($value)) {
                            $fail('La carrera seleccionada no existe.');
                        }
                    }
                },
            ],
        ]);

        // Crear el mensaje. Los mensajes de tipo "subject" llevan subject_id.
        $subjectId = $validated['recipient_type'] === 'subject' ? $validated['recipient_id'] : null;

        $message = Message::create([
            'sender_id' => Auth::id(),
            'content' => $validated['content'],
            'subject_id' => $subjectId,
        ]);

        // Determinar los destinatarios según el tipo.
        $recipients = collect();

        switch ($validated['recipient_type']) {
            case 'user':
            case 'staff':
                $recipients = User::where('id', $validated['recipient_id'])->get();
                break;

            case 'subject':
                $subject = Subject::find($validated['recipient_id']);
                if ($subject) {
                    // Todos los inscritos en el curso excepto el remitente.
                    $recipients = $subject->users()->where('users.id', '!=', Auth::id())->get();
                }
                break;

            case 'career':
                // Solo el personal puede hacer esto.
                if ($this->isStaff()) {
                    $career = Career::find($validated['recipient_id']);
                    if ($career) {
                        // Todos los estudiantes de la carrera.
                        $recipients = User::whereHas('careers', fn ($q) => $q->where('careers.id', $career->id))
                            ->where('role', 'student')
                            ->get();
                    }
                }
                break;

            case 'teachers':
                if ($this->isStaff()) {
                    $recipients = User::where('role', 'teacher')->get();
                }
                break;

            case 'all':
                if ($this->isStaff()) {
                    $recipients = User::where('id', '!=', Auth::id())->get();
                }
                break;
        }

        if ($recipients->isNotEmpty()) {
            $message->recipients()->attach($recipients->pluck('id'));
        }

        $this->reset('content');

        // Si era un mensaje nuevo, seleccionar la conversación.
        if ($validated['recipient_type'] === 'user' || $validated['recipient_type'] === 'staff') {
            $this->selectConversation('user', $validated['recipient_id']);
            $this->activeTab = 'messages';
        } elseif ($validated['recipient_type'] === 'subject') {
            $this->selectConversation('subject', $validated['recipient_id']);
            $this->activeTab = 'messages';
        }

        $this->dispatch('scroll-to-bottom');
    }
}
