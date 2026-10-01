<div>
    @php
        $effectiveStudent = $isStudent || $viewAsStudent;
    @endphp

    {{-- HEADER --}}
    <x-header title="Contenidos" subtitle="{{ $subject->name }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-select wire:model.live="subject_id" :options="$this->subjects" option-label="full_name"
                option-value="id" placeholder="Cambiar materia..." icon="o-academic-cap" class="min-w-64" />
        </x-slot:middle>
        @if (!$effectiveStudent)
            <x-slot:actions>
                <x-button label="Nueva Unidad" icon="o-plus" class="btn-primary" wire:click="addUnit" />
            </x-slot:actions>
        @endif
    </x-header>

    @if (!$isStudent)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 p-2.5 rounded-xl bg-base-100 border border-base-300">
            <div class="flex items-center gap-2">
                <x-toggle
                    label="Ver como estudiante"
                    wire:model.live="viewAsStudent"
                    class="toggle-primary toggle-sm" />
                @if ($viewAsStudent)
                    <x-badge value="Modo vista previa de estudiante activa" class="badge-warning badge-sm font-medium" />
                @endif
            </div>

            @if (!$viewAsStudent)
                <div class="flex items-center gap-2">
                    <input type="file" wire:model="upload" class="hidden" id="upload-{{ $this->id() }}">
                    <x-button label="Importar" icon="o-arrow-up-tray" class="btn-sm btn-secondary"
                        onclick="document.getElementById('upload-{{ $this->id() }}').click()" />
                    <x-button label="Exportar" icon="o-arrow-down-tray" class="btn-sm btn-accent"
                        wire:click="exportContent" spinner />
                </div>
            @endif
        </div>
    @endif

    @php
        $units = $subject->units->sortBy('order');
        if ($effectiveStudent) {
            $units = $units->where('is_visible', true);
        }
        $selectedUnit = $units->firstWhere('id', $selectedUnitId);
    @endphp

    @if ($units->isEmpty())
        <x-alert icon="o-information-circle" class="alert-info shadow-sm">
            No hay contenidos disponibles para esta materia.
        </x-alert>
    @else
        {{-- TABS MÓVIL (visible solo en pantallas pequeñas menores a lg) --}}
        <div class="lg:hidden mb-4 overflow-x-auto pb-1">
            <div class="flex gap-2 min-w-max">
                @foreach ($units as $index => $unit)
                    @php
                        $colorClass = $colors[$index % count($colors)];
                        $bgColorClass = $bgColors[$index % count($bgColors)];
                        $isActive = $unit->id == $selectedUnitId;
                    @endphp
                    <button
                        type="button"
                        wire:click="toggleTopics({{ $unit->id }})"
                        @class([
                            'flex items-center gap-2 px-4 py-2 rounded-full border text-sm font-medium transition-all whitespace-nowrap cursor-pointer',
                            'bg-primary text-primary-content border-primary shadow' => $isActive,
                            'bg-base-100 border-base-300 hover:border-primary/50' => !$isActive,
                            'opacity-60' => !$effectiveStudent && !$unit->is_visible,
                        ])>
                        <span class="w-5 h-5 rounded-full {{ $isActive ? 'bg-primary-content/20' : $bgColorClass }} text-white text-xs flex items-center justify-center font-bold">
                            {{ $unit->order }}
                        </span>
                        <span>{{ $unit->name }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- LAYOUT PRINCIPAL: sidebar + panel --}}
        <div class="flex gap-6 items-start">

            {{-- SIDEBAR (visible en lg+) --}}
            <aside class="hidden lg:block w-80 shrink-0">
                <div class="sticky top-4 space-y-2">
                    @foreach ($units as $index => $unit)
                        @php
                            $colorClass = $colors[$index % count($colors)];
                            $bgColorClass = $bgColors[$index % count($bgColors)];
                            $isActive = $unit->id == $selectedUnitId;
                            $topicsCount = $effectiveStudent
                                ? $unit->topics->where('is_visible', true)->count()
                                : $unit->topics->count();
                        @endphp
                        <div @class([
                            'group rounded-xl border transition-all duration-200 overflow-hidden',
                            'border-primary shadow-md bg-base-100' => $isActive,
                            'border-base-300 bg-base-100 hover:border-primary/40 hover:shadow-sm' => !$isActive,
                            'opacity-60' => !$effectiveStudent && !$unit->is_visible,
                        ])>
                            <button
                                type="button"
                                wire:click="toggleTopics({{ $unit->id }})"
                                class="w-full flex items-center gap-3 p-3 text-left cursor-pointer">
                                <div @class([
                                    'w-9 h-9 rounded-lg text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-xs',
                                    $bgColorClass,
                                ])>
                                    {{ $unit->order }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p @class([
                                        'font-semibold text-sm truncate',
                                        'text-primary' => $isActive,
                                    ])>{{ $unit->name }}</p>
                                    <p class="text-xs opacity-60 mt-0.5">{{ $topicsCount }} {{ \Illuminate\Support\Str::plural('tema', $topicsCount) }}</p>
                                </div>
                                @if ($isActive)
                                    <x-icon name="o-chevron-right" class="w-4 h-4 text-primary shrink-0" />
                                @endif
                            </button>

                            @if (!$effectiveStudent)
                                <div @class([
                                    'flex items-center gap-1 px-3 pb-2 border-t border-base-200 pt-2',
                                    'hidden group-hover:flex' => !$isActive,
                                ])>
                                    <x-button icon="o-pencil" class="btn-xs btn-ghost"
                                        wire:click="editUnit({{ $unit->id }})" tooltip="Editar unidad" />
                                    <x-button icon="o-trash" class="btn-xs btn-ghost text-error"
                                        wire:click="deleteUnit({{ $unit->id }})"
                                        wire:confirm="¿Estás seguro de eliminar esta unidad y todo su contenido?"
                                        tooltip="Eliminar unidad" />
                                    <x-button
                                        :icon="$unit->is_visible ? 'o-eye' : 'o-eye-slash'"
                                        class="btn-xs btn-ghost ml-auto"
                                        wire:click="toggleVisibility('unit', {{ $unit->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleVisibility('unit', {{ $unit->id }})"
                                        :tooltip="$unit->is_visible ? 'Ocultar de alumnos' : 'Mostrar a alumnos'" />
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if (!$effectiveStudent)
                        <x-button label="Nueva Unidad" icon="o-plus" class="btn-sm btn-ghost w-full border border-dashed border-base-300"
                            wire:click="addUnit" />
                    @endif
                </div>
            </aside>

            {{-- PANEL PRINCIPAL --}}
            <main class="flex-1 min-w-0">
                @if ($selectedUnit)
                    @php
                        $topics = $selectedUnit->topics->sortBy('order');
                        if ($isStudent) {
                            $topics = $topics->where('is_visible', true);
                        }
                        $unitIndex = $units->values()->search(fn($u) => $u->id === $selectedUnit->id);
                        $panelBgColor = $bgColors[$unitIndex % count($bgColors)];
                        $panelBorderColor = $colors[$unitIndex % count($colors)];
                        $panelResourceBg = $resourceBgColors[$unitIndex % count($resourceBgColors)];
                        $panelResourceText = $resourceTextColors[$unitIndex % count($resourceTextColors)];
                    @endphp

                    {{-- Encabezado de la unidad seleccionada --}}
                    <div class="mb-5 flex items-start justify-between gap-4 p-4 rounded-xl bg-base-100 border border-base-300 shadow-xs">
                        <div class="flex items-center gap-3">
                            <div class="w-11 h-11 rounded-xl {{ $panelBgColor }} text-white flex items-center justify-center font-bold text-xl shadow-xs shrink-0">
                                {{ $selectedUnit->order }}
                            </div>
                            <div>
                                <h2 class="text-xl font-bold leading-tight">{{ $selectedUnit->name }}</h2>
                                @if ($selectedUnit->description)
                                    <p class="text-sm opacity-70 mt-0.5">{{ $selectedUnit->description }}</p>
                                @endif
                            </div>
                        </div>
                        @if (!$effectiveStudent)
                            <x-button label="Nuevo Tema" icon="o-plus" class="btn-sm btn-primary shrink-0"
                                wire:click="addTopic({{ $selectedUnit->id }})" />
                        @endif
                    </div>

                    {{-- Acordeón de Temas --}}
                    @if ($topics->isEmpty())
                        <div class="py-16 text-center opacity-40 italic bg-base-100 rounded-xl border border-base-300">
                            No hay temas disponibles en esta unidad.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($topics as $topic)
                                @php
                                    $topicResources = $topic->resources;
                                    if ($effectiveStudent) {
                                        $topicResources = $topicResources->where('is_visible', true);
                                    }
                                    $isTopicOpen = $topic->id == $selectedTopicId;
                                @endphp
                                <div @class([
                                    'rounded-xl border shadow-xs overflow-hidden transition-all duration-200 bg-base-100',
                                    'border-primary/40 shadow-sm ring-1 ring-primary/20' => $isTopicOpen,
                                    'border-base-300' => !$isTopicOpen,
                                    'opacity-70' => !$effectiveStudent && !$topic->is_visible,
                                ])>
                                    {{-- Cabecera del tema (clickable para acordeón) --}}
                                    <div class="flex items-center justify-between p-3.5 hover:bg-base-200/40 transition-colors">
                                        <button
                                            type="button"
                                            wire:click="toggleResources({{ $topic->id }})"
                                            class="flex items-center gap-3 flex-1 text-left cursor-pointer">
                                            <x-badge value="{{ $topic->order }}" class="{{ $panelBgColor }} text-white border-none font-bold" />
                                            <span class="font-bold text-base">{{ $topic->name }}</span>
                                            <x-icon
                                                name="{{ $isTopicOpen ? 'o-chevron-up' : 'o-chevron-down' }}"
                                                class="w-4 h-4 opacity-50 ml-auto mr-2 shrink-0" />
                                        </button>

                                        @if (!$effectiveStudent)
                                            <div class="flex items-center gap-1 border-l border-base-200 pl-2">
                                                <x-button icon="o-pencil" class="btn-xs btn-ghost"
                                                    wire:click="editTopic({{ $topic->id }})"
                                                    tooltip="Editar tema" />
                                                <x-button icon="o-trash" class="btn-xs btn-ghost text-error"
                                                    wire:click="deleteTopic({{ $topic->id }})"
                                                    wire:confirm="¿Estás seguro de eliminar este tema?"
                                                    tooltip="Eliminar tema" />
                                                <x-button
                                                    :icon="$topic->is_visible ? 'o-eye' : 'o-eye-slash'"
                                                    class="btn-xs btn-ghost"
                                                    wire:click="toggleVisibility('topic', {{ $topic->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="toggleVisibility('topic', {{ $topic->id }})"
                                                    :tooltip="$topic->is_visible ? 'Ocultar de alumnos' : 'Mostrar a alumnos'" />
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Contenido del tema (desplegable) --}}
                                    @if ($isTopicOpen)
                                        <div class="border-t border-base-200 bg-base-100">
                                            @if ($topic->content)
                                                <div class="p-4 prose prose-sm max-w-none text-base-content/90">
                                                    {!! $topic->content !!}
                                                </div>
                                            @endif

                                            {{-- Recursos del tema --}}
                                            <div class="p-3 {{ $topic->content ? 'border-t border-base-200 bg-base-200/20' : '' }}">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-xs font-bold uppercase tracking-wider opacity-60">Recursos de aprendizaje</span>
                                                    @if (!$effectiveStudent)
                                                        <x-button label="Nuevo Recurso" icon="o-plus"
                                                            class="btn-xs btn-outline btn-primary"
                                                            wire:click="addResource({{ $topic->id }})" />
                                                    @endif
                                                </div>

                                                @if ($topicResources->isEmpty())
                                                    <p class="text-xs opacity-50 italic">No hay recursos agregados en este tema.</p>
                                                @else
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                                        @foreach ($topicResources as $resource)
                                                            <div @class([
                                                                "group flex items-center justify-between gap-2.5 $panelResourceBg hover:brightness-95 border border-base-300/80 rounded-xl p-2 transition-all relative overflow-visible",
                                                                'opacity-60' => !$effectiveStudent && !$resource->is_visible,
                                                            ])>
                                                                {{-- Contenedor del ícono un nivel fuera para mayor presencia y overflow visible --}}
                                                                <a href="{{ $resource->url }}" target="_blank"
                                                                   class="w-11 h-11 rounded-lg {{ $panelBgColor }}/20 {{ $panelResourceText }} flex items-center justify-center shrink-0 shadow-2xs relative overflow-visible group-hover:scale-105 transition-transform">
                                                                    <x-icon name="{{ $resource->icon }}" class="w-13 h-13 -rotate-15 transform scale-125 drop-shadow-xs pointer-events-none" />
                                                                </a>

                                                                {{-- Información del recurso --}}
                                                                <a href="{{ $resource->url }}" target="_blank"
                                                                   class="flex items-center gap-2 flex-1 min-w-0 hover:opacity-90 transition-opacity">
                                                                    <div class="flex-1 min-w-0">
                                                                        <p class="text-sm font-semibold truncate">{{ $resource->title }}</p>
                                                                        <p class="text-xs opacity-55 truncate">{{ parse_url($resource->url, PHP_URL_HOST) ?? $resource->url }}</p>
                                                                    </div>
                                                                </a>

                                                                {{-- Acciones en grilla de 2 por 2 --}}
                                                                @if (!$effectiveStudent)
                                                                    <div class="grid grid-cols-2 gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity shrink-0 bg-base-100/90 backdrop-blur-xs p-0.5 rounded-lg border border-base-300/60 shadow-xs z-10">
                                                                        <x-button icon="o-pencil" class="btn-xs btn-ghost btn-square tooltip-left"
                                                                            wire:click="editResource({{ $resource->id }})"
                                                                            tooltip="Editar recurso" />
                                                                        <x-button icon="o-trash" class="btn-xs btn-ghost btn-square text-error tooltip-left"
                                                                            wire:click="deleteResource({{ $resource->id }})"
                                                                            wire:confirm="¿Borrar recurso?"
                                                                            tooltip="Eliminar recurso" />
                                                                        <x-button
                                                                            :icon="$resource->is_visible ? 'o-eye' : 'o-eye-slash'"
                                                                            class="btn-xs btn-ghost btn-square tooltip-left"
                                                                            wire:click="toggleVisibility('resource', {{ $resource->id }})"
                                                                            wire:loading.attr="disabled"
                                                                            wire:target="toggleVisibility('resource', {{ $resource->id }})"
                                                                            :tooltip="$resource->is_visible ? 'Ocultar de alumnos' : 'Mostrar a alumnos'" />
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <x-alert icon="o-information-circle" class="alert-info">
                        Seleccioná una unidad del panel lateral para ver su contenido.
                    </x-alert>
                @endif
            </main>

        </div>
    @endif

    {{-- Unit Modal --}}
    <x-modal wire:model="showUnitModal" title="{{ $editingUnit ? 'Editar Unidad' : 'Nueva Unidad' }}" class="backdrop-blur">
        <x-form wire:submit="saveUnit">
            <x-input label="Nombre" wire:model="unitForm.name" />
            <x-textarea label="Descripción" wire:model="unitForm.description" />
            <x-input label="Orden" type="number" wire:model="unitForm.order" />
            <x-toggle label="Visible para los alumnos" wire:model="unitForm.is_visible" />

            <x-slot:actions>
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveUnit" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Topic Modal --}}
    <x-modal wire:model="showTopicModal" title="{{ $editingTopic ? 'Editar Tema' : 'Nuevo Tema' }}" class="backdrop-blur" size="lg">
        <x-form wire:submit="saveTopic">
            <x-input label="Nombre" wire:model="topicForm.name" />
            <x-input label="Orden" type="number" wire:model="topicForm.order" />
            <x-toggle label="Visible para los alumnos" wire:model="topicForm.is_visible" />

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
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveTopic" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    {{-- Resource Modal --}}
    <x-modal wire:model="showResourceModal" title="{{ $editingResource ? 'Editar Recurso' : 'Nuevo Recurso' }}" class="backdrop-blur">
        <x-form wire:submit="saveResource">
            <x-input label="Título del recurso" wire:model="resourceForm.title" placeholder="Ej: Guía de ejercicios, Video explicativo..." />
            <x-input label="URL / Enlace" wire:model="resourceForm.url" placeholder="https://..." />
            <x-toggle label="Visible para los alumnos" wire:model="resourceForm.is_visible" />

            <x-slot:actions>
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="saveResource" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>