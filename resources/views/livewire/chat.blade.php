<div class="h-full flex gap-2 p-2">
    <!-- Sidebar (Conversations) -->
    <div class="w-1/4 h-full flex flex-col gap-2">
        <div class="shrink-0 bg-base-100 rounded-lg border border-base-300 p-2 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-button icon="o-arrow-left" @click="window.history.back()" class="btn-sm btn-ghost" />
                <span class="font-bold truncate">{{ auth()->user()->fullname }}</span>
            </div>
        </div>
        <div class="flex-1 min-h-0 bg-base-100 rounded-lg border border-base-300 overflow-hidden flex flex-col">
            <!-- Custom Tabs Header -->
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
            <!-- Tab Content -->
            <div class="flex-1 min-h-0 overflow-hidden flex flex-col relative">
                <!-- Messages Tab -->
                @if($activeTab === 'messages')
                    <div class="absolute inset-0 overflow-y-auto p-2 space-y-2">
                        @foreach ($conversations as $key => $conversation)
                                                                                                                                            @php
                                                                                                                                                [$type, $id] = explode('_', $key);
                                                                                                                                                $model = $type === 'user' ? App\Models\User::find($id) : App\Models\Subject::find($id);
                                                                                                                                                $unreadCount = $conversation->filter(function($msg) {
                                                                                                                                                    return $msg->sender_id !== auth()->id() && 
                                                                                                                                                           $msg->recipients->first() && 
                                                                                                                                                           is_null($msg->recipients->first()->pivot->read_at);
                                                                                                                                                })->count();
                                                                                                                                            @endphp
                                                                                                                                            <div wire:click="selectConversation('{{ $type }}', {{ $id }})"
                                                                                                                                                class="p-3 rounded-lg cursor-pointer transition-colors duration-200 {{ $selectedConversation && $selectedConversation['id'] == $id && $selectedConversation['type'] == $type ? 'bg-primary text-primary-content' : 'hover:bg-base-200 bg-base-100' }}">
                                                                                                                                                                                <div class="flex justify-between items-start">
                                                                                                                                                                                    <div class="font-bold truncate max-w-[75%]">
                                                                                                                                                                                        @if($type === 'subject' && $model)
                                                                                                                                                                                            {{ $model->name }} <span class="text-xs font-normal opacity-70 block">{{ $model->career->name ?? '' }}</span>
                                                                                                                                                                                        @else
                                                                                                                                                                                            {{ $model ? $model->fullname : 'Usuario desconocido' }}
                                                                                                                                                                                        @endif
                                                                                                                                                                                    </div>
                                                                                                                                                                                    @if($unreadCount > 0)
                                                                                                                                                                                        <span class="badge badge-sm badge-error text-white">{{ $unreadCount }}</span>
                                                                                                                                                                                    @endif
                                                                                                                                                                                </div>                                                                                                                    <div class="text-xs opacity-70">                                    {{ $conversation->first()->created_at->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <!-- New Message Tab -->
                @if($activeTab === 'new')
                    <div class="absolute inset-0 overflow-y-auto p-4">
                        @if (!auth()->user()->hasRole('student'))
                            <x-select label="Carrera" wire:model.live="selectedCareerId" :options="$careers"
                                placeholder="Selecciona una carrera" class="mb-4" />
                            
                            @if($recipient_type === 'user')
                                <x-select label="Curso" wire:model.live="selectedSubjectId" :options="$subjects"
                                    placeholder="Selecciona un curso" class="mb-4" />
                            @endif
                        @endif
                        <x-select label="Enviar a" wire:model.live="recipient_type" :options="auth()->user()->hasRole('student')
                            ? [['id' => 'user', 'name' => 'Usuario'], ['id' => 'subject', 'name' => 'Curso']]
                            : [
                                ['id' => 'user', 'name' => 'Usuario'],

                                ['id' => 'subject', 'name' => 'Curso'],

                                ['id' => 'all', 'name' => 'Todos'],
                            ]" class="mb-4" />
                        @if ($recipient_type === 'user')
                            <x-select label="Usuario" wire:model.defer="recipient_id" :options="$users"
                                option-value="id" option-label="name" placeholder="Selecciona un usuario" />
                        @elseif ($recipient_type === 'subject')
                            <x-select label="Curso" wire:model.defer="recipient_id" :options="$subjects"
                                option-value="id" option-label="name" placeholder="Selecciona un curso" />
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Main Chat Area -->
    <div class="w-3/4 h-full flex flex-col">
        @if ($selectedConversation)
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
                            <div class="max-w-[70%] {{ $message->sender_id == auth()->id() ? 'bg-primary text-primary-content' : 'bg-base-100' }} rounded-lg p-3 shadow-sm">
                                @if ($message->subject)
                                    <div class="text-xs font-bold opacity-70 mb-1">
                                        📚 Curso: {{ $message->subject->name }} - {{ $message->subject->career->name ?? 'N/A' }}
                                    </div>
                                @endif
                                @if ($message->sender_id != auth()->id())
                                    <div class="text-xs font-bold opacity-70 mb-1">{{ $message->sender->fullname }}</div>
                                @endif
                                @php
                                    $content = $message->content;
                                    $content = preg_replace(
                                        '/(https?:\/\/[^\s]+)/',
                                        '<a href="$0" target="_blank" class="underline text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 break-all">$0</a>',
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
        @else
            <div class="flex-1 flex items-center justify-center bg-base-200 rounded-lg mb-2 border border-base-300">
                <div class="text-center text-base-content/50">
                    <x-icon name="o-chat-bubble-oval-left-ellipsis" class="w-16 h-16 mx-auto mb-4 opacity-20" />
                    <h3 class="text-lg font-bold">Selecciona una conversación</h3>
                    <p>o inicia una nueva desde la pestaña "Nuevo"</p>
                </div>
            </div>
        @endif
        
        <div class="bg-base-100 rounded-lg border border-base-300 p-2">
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
                    <x-button icon="o-paper-airplane" type="submit" class="btn-primary" />
                </div>
            </form>
        </div>
    </div>
</div>