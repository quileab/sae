<div class="h-full flex flex-col lg:flex-row gap-2 p-2" x-data="{ showList: true }" @messages-loaded.window="showList = false">
    <!-- Sidebar (Conversaciones) -->
    <div class="w-full lg:w-1/4 h-full flex flex-col gap-2" x-show="showList || window.innerWidth >= 1024" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-x-full" x-transition:enter-end="opacity-100 translate-x-0">
        <div class="shrink-0 bg-base-100 rounded-lg border border-base-300 p-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-arrow-left" @click="window.history.back()" class="btn-sm btn-ghost" />
                <span class="font-bold truncate">{{ auth()->user()->fullname }}</span>
            </div>
        </div>
        <div class="flex-1 min-h-0 bg-base-100 rounded-lg border border-base-300 overflow-hidden flex flex-col">
            <!-- Tabs -->
            <div class="px-2 pt-2">
                <div role="tablist" class="tabs tabs-boxed bg-base-200 grid grid-cols-2 rounded-t-lg !rounded-b-none">
                    <a role="tab" wire:click="$set('activeTab', 'messages')"
                        class="tab {{ $activeTab === 'messages' ? 'tab-active !bg-primary !text-primary-content rounded-t-lg !rounded-b-none' : '' }} transition-all duration-200">
                        <x-icon name="o-chat-bubble-left-right" class="w-4 h-4 mr-2" />
                        Mensajes
                    </a>
                    <a role="tab" wire:click="$set('activeTab', 'new')"
                        class="tab {{ $activeTab === 'new' ? 'tab-active !bg-primary !text-primary-content rounded-t-lg !rounded-b-none' : '' }} transition-all duration-200">
                        <x-icon name="o-pencil-square" class="w-4 h-4 mr-2" />
                        Nuevo
                    </a>
                </div>
            </div>
            <!-- Contenido de los tabs -->
            <div class="flex-1 min-h-0 overflow-hidden flex flex-col relative">

                <!-- Tab: Lista de conversaciones -->
                @if($activeTab === 'messages')
                    <div class="absolute inset-0 overflow-y-auto p-2 space-y-2">
                        @forelse ($conversationList as $conv)
                            <div wire:click="selectConversation('{{ $conv['type'] }}', {{ $conv['id'] }})"
                                @click="showList = false"
                                class="p-3 rounded-lg cursor-pointer transition-colors duration-200 {{ $selectedConversation && $selectedConversation['id'] == $conv['id'] && $selectedConversation['type'] == $conv['type'] ? 'bg-primary text-primary-content' : 'hover:bg-base-200 bg-base-100' }}">
                                <div class="flex justify-between items-start">
                                    <div class="font-bold truncate max-w-[75%]">
                                        {{ $conv['label'] }}
                                        @if($conv['subLabel'])
                                            <span class="text-xs font-normal opacity-70 block">{{ $conv['subLabel'] }}</span>
                                        @endif
                                    </div>
                                    @if($conv['unread'])
                                        <span class="badge badge-sm badge-error text-white">!</span>
                                    @endif
                                </div>
                                <div class="text-xs opacity-70">
                                    {{ $conv['last_date']->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center h-full text-base-content/40 py-8">
                                <x-icon name="o-chat-bubble-left-right" class="w-10 h-10 mb-2 opacity-30" />
                                <p class="text-sm">Sin conversaciones</p>
                            </div>
                        @endforelse
                    </div>
                @endif

                <!-- Tab: Nuevo mensaje -->
                @if($activeTab === 'new')
                    <div class="absolute inset-0 overflow-y-auto p-4 space-y-4">

                        @php $user = auth()->user(); @endphp

                        {{-- Tipo de destinatario (Botones de opción) --}}
                        <div>
                            <span class="label-text block mb-2 font-medium">¿A quién quieres enviar?</span>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                @foreach($this->getRecipientTypeOptions() as $option)
                                    @php
                                        $icon = match($option['id']) {
                                            'user' => 'o-user',
                                            'subject' => 'o-book-open',
                                            'career' => 'o-academic-cap',
                                            'teachers' => 'o-user-group',
                                            'all' => 'o-megaphone',
                                            default => 'o-chat-bubble-left-right'
                                        };
                                        $isActive = $recipient_type === $option['id'];
                                    @endphp
                                    <x-button
                                        icon="{{ $icon }}"
                                        label="{{ $option['name'] }}"
                                        wire:click="$set('recipient_type', '{{ $option['id'] }}')"
                                        class="{{ $isActive ? 'btn-primary' : 'btn-outline btn-primary' }} btn-sm text-xs justify-start"
                                    />
                                @endforeach
                            </div>
                        </div>

                        {{-- Para STAFF: filtros de carrera y materia --}}
                        @if($user->isStaff())
                            @if(in_array($recipient_type, ['user', 'subject', 'career']))
                                <x-select
                                    label="Carrera"
                                    wire:model.live="selectedCareerId"
                                    :options="$careers"
                                    placeholder="Todas las carreras"
                                />
                            @endif

                            @if($recipient_type === 'user')
                                <x-select
                                    label="Curso"
                                    wire:model.live="selectedSubjectId"
                                    :options="$subjects"
                                    option-value="id"
                                    option-label="name"
                                    placeholder="Selecciona un curso"
                                />
                            @endif
                        @endif

                        {{-- Para DOCENTES: filtro de carrera y curso --}}
                        @if($user->hasRole('teacher'))
                            @if($recipient_type === 'user')
                                <x-select
                                    label="Carrera"
                                    wire:model.live="selectedCareerId"
                                    :options="$careers"
                                />
                                <x-select
                                    label="Curso (para filtrar estudiantes)"
                                    wire:model.live="selectedSubjectId"
                                    :options="$subjects"
                                    option-value="id"
                                    option-label="name"
                                    placeholder="Selecciona un curso"
                                />
                            @elseif($recipient_type === 'subject')
                                <x-select
                                    label="Carrera"
                                    wire:model.live="selectedCareerId"
                                    :options="$careers"
                                />
                            @endif
                        @endif

                        {{-- Selector del destinatario específico --}}
                        @if($recipient_type === 'staff')
                            <x-select
                                label="Personal / Staff"
                                wire:model.live="recipient_id"
                                :options="$staffUsers"
                                option-value="id"
                                option-label="name"
                                placeholder="Selecciona un miembro del personal"
                            />

                        @elseif($recipient_type === 'user')
                            <x-select
                                label="Usuario"
                                wire:model.live="recipient_id"
                                :options="$users"
                                option-value="id"
                                option-label="name"
                                placeholder="Selecciona un usuario"
                            />

                        @elseif($recipient_type === 'subject')
                            <x-select
                                label="Curso"
                                wire:model.live="recipient_id"
                                :options="$subjects"
                                option-value="id"
                                option-label="name"
                                placeholder="Selecciona un curso"
                            />
                            <p class="text-xs text-base-content/60">
                                <x-icon name="o-information-circle" class="w-3 h-3 inline" />
                                Se enviará a todos los inscriptos en el curso seleccionado.
                            </p>

                        @elseif($recipient_type === 'career')
                            <x-select
                                label="Carrera"
                                wire:model.live="recipient_id"
                                :options="$careers"
                                option-value="id"
                                option-label="name"
                                placeholder="Selecciona una carrera"
                            />
                            <p class="text-xs text-base-content/60">
                                <x-icon name="o-information-circle" class="w-3 h-3 inline" />
                                Se enviará a todos los estudiantes de la carrera.
                            </p>

                        @elseif($recipient_type === 'teachers')
                            <x-alert icon="o-megaphone" class="alert-info alert-sm">
                                Se enviará a todos los docentes del sistema.
                            </x-alert>

                        @elseif($recipient_type === 'all')
                            <x-alert icon="o-megaphone" class="alert-warning alert-sm">
                                Se enviará a todos los usuarios del sistema.
                            </x-alert>
                        @endif

                        {{-- Acciones según el tipo --}}
                        @if (session()->has('error'))
                            <div class="p-3 text-sm text-error bg-error/10 rounded-lg" role="alert">
                                {{ session('error') }}
                            </div>
                        @endif
                        @error('recipient_id') <span class="text-error text-xs">{{ $message }}</span> @enderror

                        @if(
                            (in_array($recipient_type, ['user', 'subject', 'staff']) && $recipient_id) ||
                            (in_array($recipient_type, ['teachers', 'all'])) ||
                            ($recipient_type === 'career' && $recipient_id)
                        )
                            {{-- Para todos los tipos: solo abrir el hilo y redactar en el chat principal --}}
                            @php
                                $convType = $recipient_type === 'staff' ? 'user' : $recipient_type;
                                $targetId = in_array($recipient_type, ['teachers', 'all']) ? 0 : (int) $recipient_id;
                            @endphp
                            <x-button
                                label="Abrir conversación"
                                icon="o-chat-bubble-left-right"
                                wire:click="selectConversation('{{ $convType }}', {{ $targetId }})"
                                @click="$wire.activeTab = 'messages'; showList = false"
                                class="btn-primary w-full"
                                spinner
                            />
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Área principal de chat -->
    <div class="w-full lg:w-3/4 h-full flex flex-col" x-show="!showList || window.innerWidth >= 1024" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-full" x-transition:enter-end="opacity-100 translate-x-0">
        @if ($selectedConversation)
            <div class="shrink-0 bg-base-100 rounded-lg border border-base-300 p-2 mb-2 flex items-center gap-2">
                <x-button icon="o-chevron-left" @click="showList = true" class="btn-sm btn-ghost lg:hidden" label="Volver" />
                <span class="font-bold truncate">
                    @php
                        $activeName = 'Chat';
                        $convKey = $selectedConversation['type'] . '_' . $selectedConversation['id'];
                        $existingConv = collect($conversationList)->firstWhere('key', $convKey);
                        if ($existingConv) {
                            $activeName = $existingConv['label'];
                        } else {
                            if ($selectedConversation['type'] === 'user') {
                                $u = \App\Models\User::find($selectedConversation['id']);
                                if ($u) $activeName = $u->fullname;
                            } elseif ($selectedConversation['type'] === 'subject') {
                                $s = \App\Models\Subject::find($selectedConversation['id']);
                                if ($s) $activeName = $s->name;
                            } elseif ($selectedConversation['type'] === 'career') {
                                $c = \App\Models\Career::find($selectedConversation['id']);
                                if ($c) $activeName = 'Carrera: ' . $c->name;
                            } elseif ($selectedConversation['type'] === 'teachers') {
                                $activeName = 'Todos los docentes';
                            } elseif ($selectedConversation['type'] === 'all') {
                                $activeName = 'Todos los usuarios';
                            }
                        }
                    @endphp
                    {{ $activeName }}
                </span>
            </div>

            <div
                class="flex-1 overflow-y-auto p-4 bg-base-200 rounded-lg mb-2 min-h-0 border border-base-300"
                x-data="{
                    init() {
                        this.scrollToBottom();
                    },
                    scrollToBottom() {
                        this.$el.scrollTop = this.$el.scrollHeight;
                    }
                }"
                x-on:scroll-to-bottom.window="$nextTick(() => scrollToBottom())"
            >
                <div class="space-y-4">
                    @if ($receivedMessages->count() >= $amount)
                        <div
                            x-intersect="
                                currentHeight = $el.parentElement.scrollHeight;
                                $wire.loadMore().then(() => {
                                    $nextTick(() => {
                                        $el.parentElement.scrollTop = $el.parentElement.scrollTop + ($el.parentElement.scrollHeight - currentHeight);
                                    });
                                })
                            "
                            class="w-full flex justify-center py-2"
                        >
                            <span class="loading loading-spinner loading-xs opacity-50"></span>
                        </div>
                    @endif

                    @forelse ($receivedMessages->sortBy('created_at') as $message)
                        <div class="flex {{ $message->sender_id == auth()->id() ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%] lg:max-w-[70%] {{ $message->sender_id == auth()->id() ? 'bg-primary text-primary-content' : 'bg-base-100' }} rounded-lg p-3 shadow-sm">
                                @if ($message->subject)
                                    <div class="text-xs font-bold opacity-70 mb-1">
                                        📚 Curso: {{ $message->subject->name }} — {{ $message->subject->career->name ?? 'N/A' }}
                                    </div>
                                @endif
                                @if ($message->sender_id != auth()->id())
                                    <div class="text-xs font-bold opacity-70 mb-1">{{ $message->sender->fullname }}</div>
                                @endif
                                @php
                                    $content = e($message->content);
                                    $content = preg_replace(
                                        '/(https?:\/\/[^\s]+)/',
                                        '<a href="$0" target="_blank" rel="noopener noreferrer" class="underline text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 break-all">$0</a>',
                                        $content
                                    );
                                @endphp
                                <p class="text-sm whitespace-pre-wrap">{!! $content !!}</p>
                                <div class="text-xs opacity-70 text-right mt-1">
                                    {{ $message->created_at->format('H:i') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full items-center justify-center text-base-content/50">
                            <div class="text-center">
                                <x-icon name="o-chat-bubble-left-right" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                                <p>No hay mensajes en esta conversación</p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="bg-base-100 rounded-lg border border-base-300 p-2 shrink-0">
                <form wire:submit.prevent="sendMessage">
                    @if (session()->has('error'))
                        <div class="p-3 mb-2 text-sm text-error bg-error/10 rounded-lg" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <x-textarea placeholder="Escribe tu mensaje..." wire:model.defer="content" rows="1" class="w-full !min-h-[2.5rem] max-h-32" />
                        </div>
                        <x-button icon="o-paper-airplane" type="submit" class="btn-primary" spinner="sendMessage" />
                    </div>
                </form>
            </div>
        @else
            <div class="flex-1 flex items-center justify-center bg-base-200 rounded-lg mb-2 border border-base-300 lg:flex hidden">
                <div class="text-center text-base-content/50">
                    <x-icon name="o-chat-bubble-oval-left-ellipsis" class="w-16 h-16 mx-auto mb-4 opacity-20" />
                    <h3 class="text-lg font-bold">Selecciona una conversación</h3>
                    <p>o inicia una nueva desde la pestaña "Nuevo"</p>
                </div>
            </div>

            <div class="flex-1 flex items-center justify-center bg-base-200 rounded-lg mb-2 border border-base-300 lg:hidden" x-show="!showList" @click="showList = true">
                <div class="text-center text-base-content/50">
                    <x-icon name="o-arrow-left" class="w-12 h-12 mx-auto mb-2 opacity-20" />
                    <p>Toca para ver conversaciones</p>
                </div>
            </div>
        @endif
    </div>
</div>