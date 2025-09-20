<div>
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-2">
        <div class="lg:col-span-1">
            <x-card title="Mensajes">
                @foreach ($conversations as $key => $conversation)
                    @php
                        [$type, $id] = explode('_', $key);
                        $model = $type === 'user' ? App\Models\User::find($id) : App\Models\Subject::find($id);
                    @endphp
                    <div wire:click="selectConversation('{{ $type }}', {{ $id }})"
                        class="p-2 rounded-lg cursor-pointer {{ $selectedConversation && $selectedConversation['id'] == $id && $selectedConversation['type'] == $type ? 'bg-blue-900 text-white' : 'hover:bg-green-900 text-white' }}">
                        <div class="font-bold">{{ $model->name ?? $model->fullname }}</div>
                        <div class="text-xs text-gray-500">{{ $conversation->first()->created_at->format('d/m/Y H:i') }}
                        </div>
                    </div>
                @endforeach
            </x-card>
        </div>
        <div class="lg:col-span-2">
            @if ($selectedConversation)
                <x-card>
                    <div class="space-y-4">
                        @forelse ($receivedMessages->sortBy('created_at') as $message)
                            <div class="flex {{ $message->sender_id == auth()->id() ? 'justify-end' : 'justify-start' }}">
                                <div
                                    class="text-white {{ $message->sender_id == auth()->id() ? 'bg-green-900' : 'bg-blue-900' }} rounded-lg p-3 max-w-xs">
                                    {{-- Si es un mensaje de otro usuario --}}
                                    @if($message->sender_id != auth()->id())
                                        <div class="font-xs text-gray-400">{{ $message->sender->fullname }}</div>
                                    @endif
                                    <p class="text-sm">{{ $message->content }}</p>
                                    <div class="text-xs text-gray-500 text-right">{{ $message->created_at->format('H:i') }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-gray-500">Sin mensajes.</div>
                        @endforelse
                    </div>
                </x-card>
            @else
                <x-card title="Selecciona una conversación" />
            @endif
        </div>
        <div>
            <form wire:submit.prevent="sendMessage">
                <x-card title="Enviar Mensaje">
                    <x-textarea label="Mensaje" wire:model.defer="content" rows="3" />

                    <x-select label="Enviar a" wire:model.live="recipient_type"
                        :options="auth()->user()->hasRole('student') ? [['id' => 'user', 'name' => 'Usuario'], ['id' => 'subject', 'name' => 'Curso']] : [['id' => 'user', 'name' => 'Usuario'], ['id' => 'subject', 'name' => 'Curso'], ['id' => 'all', 'name' => 'Todos']]" />

                    @if ($recipient_type === 'user')
                        <x-select label="Usuario" wire:model.defer="recipient_id" :options="$users" option-value="id"
                            option-label="name" placeholder="Selecciona un usuario" />
                    @elseif ($recipient_type === 'subject')
                        <x-select label="Curso" wire:model.defer="recipient_id" :options="$subjects"
                            placeholder="Selecciona un curso" />
                    @endif

                    <x-slot:actions>
                        <x-button label="Enviar" type="submit" class="btn-primary" />
                    </x-slot:actions>
                </x-card>
            </form>
        </div>
    </div>
</div>