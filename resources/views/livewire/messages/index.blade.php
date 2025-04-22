<?php

use App\Models\Subject;
use App\Models\User;
use App\Models\ClassSession;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;
use Mary\Traits\Cacheable;
use Livewire\Attributes\On;

new class extends Component {
    use WithPagination;
    use WithFileUploads;

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
        if(Auth::user()->hasAnyRole(['admin', 'principal', 'administrative'])) {
            $this->tabs = [
                'subjects-tab' => 'Materias',
                'users-tab' => 'Contactos',
                'global-tab' => 'Global'
            ];
        }
        $this->loadUserSubjects();
    }

    public function loadUserSubjects()
    {
        $this->userSubjects=Auth::user()->subjects()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })->get();
        // Check If subjects array is empty, load global messages
        if(empty($this->userSubjects)) {
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
                    $query->where('firstname', 'like', '%' . $this->search . '%')
                        ->orWhere('lastname', 'like', '%' . $this->search . '%');
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
        if($this->selectedTab === 'global-tab') {
            $this->messageType = 'global';
        }
    }

    public function updatedSelectedSubject()
    {
        $this->loadSubjectContacts();
    }

    public function selectSubject(\App\Models\Subject$subjectId)
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
        //dd($this->messages);
    }

    public function sendMessage()
        {
            // $message=$this->validate([
            //     'messageContent' => 'required|string|max:500',
            //     'attachment' => 'nullable|file|max:2048', // Ejemplo: máximo 2MB
            // ]);
            $message = new \App\Models\Message();
            $message->sender_id = Auth::id();
            $message->content = $this->messageContent;
            $message->messageType = $this->messageType;
            $message->subject_id = $this->selectedSubject?->id; // Guardar subject_id si existe
    
            if ($this->attachment) {
                $path = $this->attachment->store('attachments', 'public'); // Guarda en storage/app/public/attachments
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
                    // Los mensajes de materia se guardan con receiver_id null y subject_id lleno
                    $message->receiver_id = null;
                    $message->save();
                    $subjectUsers = $this->selectedSubject->users;
                    foreach ($subjectUsers as $user) {
                        \App\Models\MessageRead::create([
                            'message_id' => $message->id,
                            'user_id' => $user->id,
                            'read_at' => null,
                        ]);
                    }
                    $this->dispatch('messageSent');
                    break;
                case 'global':
                    // Los mensajes globales se guardan con receiver_id y subject_id null
                    $message->receiver_id = null;
                    $message->subject_id = null;
                    $message->save();
                    $allUsers = User::all();
                    foreach ($allUsers as $user) {
                        \App\Models\MessageRead::create([
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
            $this->dispatch('scroll-to-bottom'); // Dispara el evento de navegador
        }

        #[On('messageSent')]
        public function refreshMessages()
        {
            $this->resetPage(); // Opcional: Si estás usando paginación, resetea la página a la primera
            $this->getMessages(); // Vuelve a cargar los mensajes
        }

        //# [ \Cacheable]
        public function getMessages()
        {
            $query = \App\Models\Message::query()
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

            //$this->messages = $query->latest()->paginate(10);
            return $query->latest()->paginate(10);
        }

}; ?>

<div class="flex h-screen overflow-clip -mt-5">
    <!-- Sidebar -->
    <div class="w-72 bg-primary/10 flex flex-col h-screen overflow-y-auto p-1">
        <!-- Tabs -->
        <x-input placeholder="Buscar..." wire:model.live.debounce="search"
            icon="o-magnifying-glass" clearable />
        <x-tabs wire:model.live="selectedTab" active-class="bg-primary rounded" label-class="font-semibold"
            label-div-class="bg-primary/25 mt-1">
            <x-tab name="users-tab" label="{{ $tabs['users-tab'] }}">
                <div>
                    @forelse($subjectContacts as $subjectId => $contact)
                        <div @class([
                            "flex items-center my-1 border-b border-white/10 cursor-pointer hover:bg-primary py-1 rounded-l-full pl-1",
                            'font-semibold bg-white/30'=>$contact->user->hasNotRole('student')
                            ])
                            wire:click="selectContact({{ $contact->user->id }})">
                            <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white text-semibold mr-2">
                                {{ strtoupper(substr($contact->user->firstname ?? '', 0, 1)) }}
                            </div>
                            <span>{{ $contact->user->fullname }}</span>
                        </div>
                    @empty
                        <div class="p-4 text-center text-gray-500">Sin contactos</div>
                    @endforelse
                </div>
            </x-tab>
            <x-tab name="subjects-tab" label="{{ $tabs['subjects-tab'] }}">
                <div>
                    @forelse($userSubjects as $subject)
                        <div class="flex items-center my-1 border-b border-white/10 cursor-pointer hover:bg-primary/10 py-1" wire:click="selectSubject({{ $subject->id }})">
                            <span class="text-xs rotate-90 text-primary">{{ $subject->id}}</span> {{ $subject->name }}
                        </div>
                    @empty
                        <div class="p-4 text-center text-gray-500">Sin materias</div>
                    @endforelse
                </div>
            </x-tab>
            @if(isset($tabs['global-tab']))
            <x-tab name="global-tab" label="{{ $tabs['global-tab'] }}">
                <div>Mensajes globales Seleccionados</div>
            </x-tab>
            @endif
        </x-tabs>

    </div>

    <!-- Chat Area -->
    <div class="flex-1 flex flex-col">
        <!-- Chat Header -->
        <div class="border-b p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center text-primary font-semibold">
                {{ strtoupper(substr($contact->user->lastname ?? '', 0, 1)) }}
            </div>
            <div>
                <div class="font-medium">{{ $contact->user->fullname }}</div>
                <div class="text-sm text-gray-500">
                    {{ optional($userSubjects->firstWhere('id', $selectedSubject))->name }}
                </div>
            </div>
        </div>
        <!-- Messages -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="messages-container">
            @forelse($this->getMessages() as $message)
                <div class="flex {{ $message->sender_id === auth()->id() ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[70%] text-white {{ $message->sender_id === auth()->id() ? 'bg-blue-800' : 'bg-green-800' }} rounded-lg p-3 shadow">
                        <p>{{ $message->content }}</p>
                        <div class="text-xs text-white/60 text-right mt-1">
                            {{ $message->created_at->format('H:i') }}
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-4 text-center text-gray-500">No hay mensajes</div>
            @endforelse
        </div>

        <!-- Message Type Selector and Input -->
        <div class="p-4">
            <form wire:submit="sendMessage" class="flex-1 gap-2">
                <x-input wire:model.live.debounce.500ms="messageContent" placeholder="Mensaje" class="w-full">
                    @if(!empty($this->messageContent))
                    <x-slot:prepend>
                        <x-dropdown>
                            <x-slot:trigger>
                                <x-button icon="o-paper-clip" class="join-item btn-primary" />
                            </x-slot:trigger>
                            <x-menu-item @click.stop="">
                                <x-file wire:model="attachment" class="join-item" />
                            </x-menu-item>
                        </x-dropdown>
                    </x-slot:prepend>
                    <x-slot:append>
                        <x-button type="submit" icon="o-paper-airplane" 
                            class="join-item btn-primary" spinner="sendMessage"
                        />
                    </x-slot:append>
                    @endif
                </x-input>
            </form>
        </div>
    </div>
</div>

<script>
    window.addEventListener('scroll-to-bottom', event => {
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    });

    // También podrías querer hacer scroll al cargar la página inicialmente o al cambiar de conversación
    document.addEventListener('livewire:load', () => {
        const messagesContainer = document.getElementById('messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        // Opcional: Hacer scroll después de cada actualización de Livewire (si es necesario)
        Livewire.hook('element.updated', (el, component) => {
            if (document.getElementById('messages-container')) {
                document.getElementById('messages-container').scrollTop = document.getElementById('messages-container').scrollHeight;
            }
        });
    });
</script>