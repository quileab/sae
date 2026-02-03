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