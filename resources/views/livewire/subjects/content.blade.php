<div>
    <x-header :title="'Contenido de ' . $subject->name" separator>
        <x-slot:actions>
            <x-button label="Administrar Contenidos" icon="o-book-open"
                link="{{ route('subjects.content-manager', ['subject' => $subject->id]) }}" class="btn-primary" />
            <x-button label="Nueva Unidad" icon="o-plus" wire:click="openUnitModal" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-4 mt-4">
        @foreach($units as $unit)
            <x-card :title="$unit->name" :subtitle="$unit->description">
                <x-slot:actions>
                    <x-button label="Nuevo Tema" icon="o-plus" wire:click="openTopicModal({{ $unit->id }})" />
                    <x-button label="Editar" icon="o-pencil" wire:click="editUnit({{ $unit->id }})" />
                    <x-button icon="o-eye" wire:click="toggleUnitVisibility({{ $unit->id }})"
                        class="btn-sm {{ $unit->is_visible ? 'btn-success' : 'btn-warning' }}" />
                    <x-button label="Eliminar" icon="o-trash" wire:click="deleteUnit({{ $unit->id }})" />
                </x-slot:actions>

                <div class="mt-4">
                    @foreach($unit->topics as $topic)
                        <div class="flex items-center justify-between p-2 border-b">
                            <div>{{ $topic->name }}</div>
                            <div>
                                <x-button label="Recursos" icon="o-link" wire:click="openResourceModal({{ $topic->id }})" />
                                <x-button label="Editar" icon="o-pencil" wire:click="editTopic({{ $topic->id }})" />
                                <x-button icon="o-eye" wire:click="toggleTopicVisibility({{ $topic->id }})"
                                    class="btn-sm {{ $topic->is_visible ? 'btn-success' : 'btn-warning' }}" />
                                <x-button label="Eliminar" icon="o-trash" wire:click="deleteTopic({{ $topic->id }})" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- Modals will go here --}}

    <x-modal wire:model="unitModal" title="Unidad">
        <x-form wire:submit.prevent="saveUnit">
            <x-input label="Nombre" wire:model="unitForm.name" />
            <x-textarea label="Descripción" wire:model="unitForm.description" />
            <x-input label="Orden" wire:model="unitForm.order" type="number" />
            <x-toggle label="Visible para estudiantes" wire:model="unitForm.is_visible" />

            <x-slot:actions>
                <x-button label="Cancelar" wire:click="closeUnitModal" />
                <x-button label="Guardar" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="topicModal" title="Tema">
        <x-form wire:submit.prevent="saveTopic">
            <x-input label="Nombre" wire:model="topicForm.name" />
            <x-textarea label="Contenido" wire:model="topicForm.content" />
            <x-input label="Orden" wire:model="topicForm.order" type="number" />
            <x-toggle label="Visible para estudiantes" wire:model="topicForm.is_visible" />

            <x-slot:actions>
                <x-button label="Cancelar" wire:click="closeTopicModal" />
                <x-button label="Guardar" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="resourceModal" title="Recursos">
        <x-form wire:submit.prevent="saveResource">
            <x-input label="Título" wire:model="resourceForm.title" />
            <x-input label="URL" wire:model="resourceForm.url" />
            <x-toggle label="Visible para estudiantes" wire:model="resourceForm.is_visible" />

            <x-slot:actions>
                <x-button label="Cancelar" wire:click="closeResourceModal" />
                <x-button label="Guardar" type="submit" class="btn-primary" />
            </x-slot:actions>
        </x-form>

        <x-hr />

        @if($selectedTopic)
            <div class="mt-4">
                @foreach($selectedTopic->resources as $resource)
                    <div class="flex items-center justify-between p-2 border-b">
                        <a href="{{ $resource->url }}" target="_blank">{{ $resource->title }}</a>
                        <x-button icon="o-eye" wire:click="toggleResourceVisibility({{ $resource->id }})"
                            class="btn-sm {{ $resource->is_visible ? 'btn-success' : 'btn-warning' }}" />
                        <x-button icon="o-trash" wire:click="deleteResource({{ $resource->id }})" />
                    </div>
                @endforeach
            </div>
        @endif
    </x-modal>
</div>