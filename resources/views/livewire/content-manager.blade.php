<div>
    <x-header title="Contenidos" subtitle="{{ $subject->name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-select wire:model.live="subject_id" :options="$this->subjects" option-label="full_name"
                option-value="id" placeholder="Cambiar materia..." icon="o-academic-cap" class="min-w-64" />
        </x-slot:middle>
        @if(!auth()->user()->hasRole('student'))
            <x-slot:actions>
                <x-button label="Nueva Unidad" icon="o-plus" class="btn-primary" wire:click="addUnit" />
            </x-slot:actions>
        @endif
    </x-header>

    @if(!auth()->user()->hasRole('student'))
        <div class="mb-6 flex justify-end gap-2">
            <input type="file" wire:model="upload" class="hidden" id="upload-{{ $this->id() }}">
            <x-button label="Importar" icon="o-arrow-up-tray" class="btn-ghost btn-sm" onclick="document.getElementById('upload-{{ $this->id() }}').click()" />
            <x-button label="Exportar" icon="o-arrow-down-tray" class="btn-ghost btn-sm" wire:click="exportContent" spinner />
        </div>
    @endif

    @php
        $units = $subject->units->sortBy('order');
        if(auth()->user()->hasRole('student')) {
            $units = $units->where('is_visible', true);
        }
    @endphp

    @if ($units->isEmpty())
        <x-alert icon="o-information-circle" class="alert-info shadow-sm">No hay contenidos disponibles para esta materia.</x-alert>
    @else
        <div class="space-y-6">
            @php
                $colors = [
                    'border-l-violet-500', 
                    'border-l-blue-500', 
                    'border-l-emerald-500', 
                    'border-l-amber-500', 
                    'border-l-orange-500', 
                    'border-l-red-500'
                ];
                $bgColors = [
                    'bg-violet-500', 
                    'bg-blue-500', 
                    'bg-emerald-500', 
                    'bg-amber-500', 
                    'bg-orange-500', 
                    'bg-red-500'
                ];
            @endphp

            @foreach ($units as $index => $unit)
                @php 
                    $colorClass = $colors[$index % count($colors)]; 
                    $bgColorClass = $bgColors[$index % count($bgColors)];
                @endphp
                
                <div @class([
                    "bg-base-100 rounded-xl shadow-sm border border-base-300 transition-all duration-300",
                    "border-l-8 $colorClass",
                    "opacity-60 grayscale-[0.5]" => !auth()->user()->hasRole('student') && !$unit->is_visible
                ])>
                    {{-- Unit Header --}}
                    <div class="p-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-4 flex-1 min-w-[200px]">
                            <div class="w-12 h-12 rounded-lg {{ $bgColorClass }} text-white flex items-center justify-center font-bold text-xl shadow-sm">
                                {{ $unit->order }}
                            </div>
                            <div>
                                <h3 class="text-lg font-bold flex items-center gap-2">
                                    {{ $unit->name }}
                                    @if(!auth()->user()->hasRole('student') && !$unit->is_visible)
                                        <x-badge value="Oculto" class="badge-ghost badge-sm italic" />
                                    @endif
                                </h3>
                                <p class="text-sm opacity-70 line-clamp-1">{{ $unit->description }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            @if(!auth()->user()->hasRole('student'))
                                <x-button icon="o-pencil" class="btn-circle btn-ghost btn-sm" wire:click="editUnit({{ $unit->id }})" tooltip="Editar Unidad" />
                                <x-button icon="o-trash" class="btn-circle btn-ghost btn-sm text-error" wire:click="deleteUnit({{ $unit->id }})"
                                    wire:confirm="¿Estás seguro de eliminar esta unidad y todo su contenido?" tooltip="Eliminar" />
                                <div class="divider divider-horizontal mx-1"></div>
                            @endif
                            
                            <x-button label="{{ $unit->id == $selectedUnitId ? 'Cerrar' : 'Ver Temas' }}" 
                                icon="{{ $unit->id == $selectedUnitId ? 'o-chevron-up' : 'o-chevron-down' }}"
                                class="btn-sm {{ $unit->id == $selectedUnitId ? 'btn-active' : 'btn-ghost' }}" 
                                wire:click="toggleTopics({{ $unit->id }})" />
                            
                            @if(!auth()->user()->hasRole('student'))
                                <x-button label="Nuevo Tema" icon="o-plus" class="btn-sm btn-primary"
                                    wire:click="addTopic({{ $unit->id }})" />
                                <x-toggle wire:click="toggleUnitVisibility({{ $unit->id }})" :checked="$unit->is_visible" class="ml-2" tooltip="Visibilidad" />
                            @endif
                        </div>
                    </div>

                    {{-- Topics Content --}}
                    @if ($unit->id == $selectedUnitId)
                        <div class="p-4 pt-0 space-y-4 border-t border-base-200 mt-2 bg-base-200/30 rounded-b-xl">
                            @php
                                $topics = $unit->topics->sortBy('order');
                                if(auth()->user()->hasRole('student')) {
                                    $topics = $topics->where('is_visible', true);
                                }
                            @endphp

                            @if ($topics->isEmpty())
                                <div class="py-8 text-center opacity-50 italic">No hay temas disponibles en esta unidad.</div>
                            @else
                                <div class="grid gap-4 mt-4">
                                    @foreach ($topics as $topic)
                                        <div @class([
                                            "bg-base-100 p-4 rounded-lg border border-base-300 shadow-sm",
                                            "opacity-75" => !auth()->user()->hasRole('student') && !$topic->is_visible
                                        ])>
                                            <div class="flex justify-between items-start mb-2">
                                                <div class="flex items-center gap-2">
                                                    <x-badge value="{{ $topic->order }}" class="{{ $bgColorClass }} text-white border-none" />
                                                    <h4 class="font-bold text-md">{{ $topic->name }}</h4>
                                                    @if(!auth()->user()->hasRole('student') && !$topic->is_visible)
                                                        <x-icon name="o-eye-slash" class="w-4 h-4 opacity-50" />
                                                    @endif
                                                </div>
                                                @if(!auth()->user()->hasRole('student'))
                                                    <div class="flex gap-1">
                                                        <x-button icon="o-pencil" class="btn-xs btn-ghost" wire:click="editTopic({{ $topic->id }})" />
                                                        <x-button icon="o-trash" class="btn-xs btn-ghost text-error"
                                                            wire:click="deleteTopic({{ $topic->id }})"
                                                            wire:confirm="¿Estás seguro de eliminar este tema?" />
                                                        <x-toggle wire:click="toggleTopicVisibility({{ $topic->id }})" :checked="$topic->is_visible" class="toggle-xs" />
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="prose prose-sm max-w-none mb-4 text-base-content/80">
                                                {!! $topic->content !!}
                                            </div>

                                            @php
                                                $resources = $topic->resources;
                                                if(auth()->user()->hasRole('student')) {
                                                    $resources = $resources->where('is_visible', true);
                                                }
                                            @endphp

                                            <div class="border-t border-base-200 pt-3 flex flex-wrap items-center gap-2">
                                                <span class="text-xs font-bold opacity-50 uppercase tracking-widest mr-2">Recursos:</span>
                                                @foreach ($resources as $resource)
                                                    <div class="group relative flex items-center gap-2 bg-base-200 px-3 py-1.5 rounded-full border border-base-300 hover:border-primary transition-colors">
                                                        <a href="{{ $resource->url }}" target="_blank" class="flex items-center gap-2 text-sm hover:text-primary transition-colors">
                                                            <x-icon name="{{ str_contains($resource->url, 'drive.google.com') ? 'o-cloud' : (str_contains($resource->url, 'youtube.com') ? 'o-play-circle' : 'o-link') }}" class="w-4 h-4 text-primary" />
                                                            <span class="max-w-[150px] truncate font-medium">{{ $resource->title }}</span>
                                                        </a>
                                                        @if(!auth()->user()->hasRole('student'))
                                                            <div class="flex gap-1">
                                                                <button wire:click="editResource({{ $resource->id }})" class="opacity-0 group-hover:opacity-100 transition-opacity hover:text-primary"><x-icon name="o-pencil" class="w-3 h-3" /></button>
                                                                <button wire:click="deleteResource({{ $resource->id }})" wire:confirm="¿Borrar recurso?" class="opacity-0 group-hover:opacity-100 transition-opacity hover:text-error"><x-icon name="o-trash" class="w-3 h-3" /></button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                
                                                @if(!auth()->user()->hasRole('student'))
                                                    <x-button label="Recurso" icon="o-plus" class="btn-xs btn-outline btn-primary rounded-full"
                                                        wire:click="addResource({{ $topic->id }})" />
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Unit Modal --}}
    <x-modal wire:model="showUnitModal" title="{{ $editingUnit ? 'Editar Unidad' : 'Nueva Unidad' }}" class="backdrop-blur">
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
    <x-modal wire:model="showTopicModal" title="{{ $editingTopic ? 'Editar Tema' : 'Nuevo Tema' }}" class="backdrop-blur" size="lg">
        <x-form wire:submit="saveTopic">
            <x-input label="Nombre" wire:model="topicForm.name" class="mb-4" />
            <x-input label="Orden" type="number" wire:model="topicForm.order" class="mb-4" />
            <x-toggle label="Visible para los alumnos" wire:model="topicForm.is_visible" class="mb-4" />

            @php
                $config = [
                    'plugins' => 'autoresize',
                    'min_height' => 200,
                    'max_height' => 400,
                    'statusbar' => false,
                    'toolbar' => 'undo redo | h1 h2 h3 | bold italic underline | bullist numlist | quicktable link',
                    'quickbars_selection_toolbar' => 'bold italic link',
                ];
            @endphp

            <x-editor label="Contenido pedagógico" wire:model="topicForm.content" :config="$config" />

            <x-slot:actions>
                <x-button label="Cancelar" @click="showTopicModal = false" />
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveTopic" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Resource Modal --}}
    <x-modal wire:model="showResourceModal" title="{{ $editingResource ? 'Editar Recurso' : 'Nuevo Recurso' }}" class="backdrop-blur">
        <x-form wire:submit="saveResource">
            <x-input label="Título del recurso" wire:model="resourceForm.title" placeholder="Ej: Guía de ejercicios, Video explicativo..." class="mb-4" />
            <x-input label="URL / Enlace" wire:model="resourceForm.url" placeholder="https://..." class="mb-4" />
            <x-toggle label="Visible para los alumnos" wire:model="resourceForm.is_visible" class="mb-4" />

            <x-slot:actions>
                <x-button label="Cancelar" @click="showResourceModal = false" />
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveResource" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>