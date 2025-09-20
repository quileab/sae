<div>
    <x-header title="Administrador de Contenido para {{ $subject->name }}" />

    <x-card title="Unidades" shadow separator>
        <x-slot:menu>
            <x-button label="Nueva Unidad" icon="o-plus" class="btn-primary" wire:click="addUnit" />
        </x-slot:menu>

        @if ($subject->units->isEmpty())
            <x-alert icon="o-information-circle" class="alert-info">No hay unidades creadas para esta materia.</x-alert>
        @else
            @foreach ($subject->units->sortBy('order') as $unit)
                <x-card title="Unidad {{ $unit->order }}: {{ $unit->name }}" subtitle="{{ $unit->description }}">
                    <x-slot:menu>
                        <x-button icon="o-pencil" class="btn-sm btn-ghost" wire:click="editUnit({{ $unit->id }})" />
                        <x-button icon="o-trash" class="btn-sm btn-ghost text-red-500" wire:click="deleteUnit({{ $unit->id }})"
                            wire:confirm="¿Estás seguro de eliminar esta unidad y todo su contenido?" />
                        <x-toggle wire:click="toggleUnitVisibility({{ $unit->id }})" :checked="$unit->is_visible" />
                    </x-slot:menu>

                    <x-slot:actions>
                        <x-button label="{{ $unit->id == $selectedUnitId ? 'Ocultar Temas' : 'Ver Temas' }}" icon="o-eye"
                            class="btn-sm btn-outline" wire:click="toggleTopics({{ $unit->id }})" />
                        <x-button label="Nuevo Tema" icon="o-plus" class="btn-sm btn-primary"
                            wire:click="addTopic({{ $unit->id }})" />
                    </x-slot:actions>

                    @if ($unit->id == $selectedUnitId)
                        {{-- topic card --}}
                        <div class="space-y-2 border-2 border-gray-500 rounded-lg">
                            @if ($unit->topics->isEmpty())
                                <x-alert icon="o-information-circle" class="alert-info">No hay temas creados para esta unidad.</x-alert>
                            @else
                                @foreach ($unit->topics->sortBy('order') as $topic)
                                    <x-card title="Tema {{ $topic->order }}: {{ $topic->name }}">
                                        <x-slot:menu>
                                            <x-button icon="o-pencil" class="btn-sm btn-ghost" wire:click="editTopic({{ $topic->id }})" />
                                            <x-button icon="o-trash" class="btn-sm btn-ghost text-red-500"
                                                wire:click="deleteTopic({{ $topic->id }})"
                                                wire:confirm="¿Estás seguro de eliminar este tema y todos sus recursos?" />
                                            <x-toggle wire:click="toggleTopicVisibility({{ $topic->id }})" :checked="$topic->is_visible" />
                                        </x-slot:menu>
                                        <div class="prose max-w-none">{!! $topic->content !!}</div>
                                        <x-slot:actions>
                                            <x-button
                                                label="{{ $topic->id == $selectedTopicId ? 'Ocultar Recursos' : 'Gestionar Recursos' }}"
                                                icon="o-link" class="btn-sm btn-outline" wire:click="toggleResources({{ $topic->id }})" />
                                            <x-button label="Nuevo Recurso" icon="o-plus" class="btn-sm btn-primary"
                                                wire:click="addResource({{ $topic->id }})" />
                                        </x-slot:actions>

                                        @if ($topic->id == $selectedTopicId)
                                            <div class="mt-2 space-y-2 flex gap-1">
                                                @if ($topic->resources->isEmpty())
                                                    <x-alert icon="o-information-circle" class="alert-info">No hay recursos creados para este
                                                        tema.</x-alert>
                                                @else
                                                    {{-- resource card --}}
                                                    @foreach ($topic->resources as $resource)
                                                        <div class="flex justify-between items-center p-2 border border-gray-500 rounded-lg max-w-64">
                                                            <a href="{{ $resource->url }}" target="_blank" class="text-blue-500 hover:underline">
                                                                <h5 class="text-md font-semibold">{{ $resource->title }}</h5>
                                                            </a>
                                                            <div class="flex flex-col items-center">
                                                                <x-button icon="o-pencil" class="btn-sm btn-ghost"
                                                                    wire:click="editResource({{ $resource->id }})" />
                                                                <x-button icon="o-trash" class="btn-sm btn-ghost text-red-500"
                                                                    wire:click="deleteResource({{ $resource->id }})"
                                                                    wire:confirm="¿Estás seguro de eliminar este recurso?" />
                                                                <x-toggle wire:click="toggleResourceVisibility({{ $resource->id }})"
                                                                    :checked="$resource->is_visible" />
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        @endif
                                    </x-card>
                                @endforeach
                            @endif
                        </div>
                    @endif
                </x-card>
            @endforeach

        @endif
    </x-card>

    {{-- Unit Modal --}}
    <x-modal wire:model="showUnitModal" title="{{ $editingUnit ? 'Editar Unidad' : 'Nueva Unidad' }}">
        <x-form wire:submit="saveUnit">
            <x-input label="Nombre" wire:model="unitForm.name" class="mb-4" />
            <x-textarea label="Descripción" wire:model="unitForm.description" class="mb-4" />
            <x-input label="Orden" type="number" wire:model="unitForm.order" class="mb-4" />
            <x-toggle label="Visible para los alumnos" wire:model="unitForm.is_visible" class="mb-4" />

            <x-slot:actions>
                <x-button label="Cancelar" @click="showUnitModal = false" />
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveUnit" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Topic Modal --}}
    <x-modal wire:model="showTopicModal" title="{{ $editingTopic ? 'Editar Tema' : 'Nuevo Tema' }}">
        <x-form wire:submit="saveTopic">
            <x-input label="Nombre" wire:model="topicForm.name" class="mb-4" />
            <x-input label="Orden" type="number" wire:model="topicForm.order" class="mb-4" />
            <x-toggle label="Visible para los alumnos" wire:model="topicForm.is_visible" class="mb-4" />

            @php
                $config = [
                    'plugins' => 'autoresize',
                    'min_height' => 150,
                    'max_height' => 250,
                    'statusbar' => false,
                    'toolbar' => 'undo redo | h1 h2 h3 quicktable',
                    'quickbars_selection_toolbar' => 'bold italic link',
                ];
            @endphp

            <x-editor label="Contenido" wire:model="topicForm.content" :config="$config" />

            <x-slot:actions>
                <x-button label="Cancelar" @click="showTopicModal = false" />
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveTopic" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Resource Modal --}}
    <x-modal wire:model="showResourceModal" title="{{ $editingResource ? 'Editar Recurso' : 'Nuevo Recurso' }}">
        <x-form wire:submit="saveResource">
            <x-input label="Título" wire:model="resourceForm.title" class="mb-4" />
            <x-input label="URL" wire:model="resourceForm.url" class="mb-4" />
            <x-toggle label="Visible para los alumnos" wire:model="resourceForm.is_visible" class="mb-4" />

            <x-slot:actions>
                <x-button label="Cancelar" @click="showResourceModal = false" />
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveResource" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>