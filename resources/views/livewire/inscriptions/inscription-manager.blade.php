<div class="pb-10">
    <!-- HEADER -->
    <x-header title="{{ $labels['label_subjects'] }} {{ $this->targetUser->name }}" progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
    </x-header>
    {{-- if return with message --}}
    @if (session()->has('success'))
        <x-alert icon="o-information-circle" title="{{ session('success') }}" class="alert-success" dismissible />
    @endif

    <!-- TABLE  -->
    <x-card>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-12 sticky top-0 z-20 backdrop-blur-md
        pb-2 border-b border-black/20 dark:border-white/20 items-end">
            <div class="md:col-span-3">
                <x-select wire:model.live="inscription_id" label="{{ $labels['label_subjects'] }} a" :options="$this->inscriptions"
                    option-value="id" option-label="description" />
            </div>
            <div class="md:col-span-4">
                <x-select wire:model.live="career_id" label="{{ $labels['label_career'] }}" :options="$this->careers" />
            </div>

            @if($this->targetUser->enabled)
                <div class="md:col-span-5">
                    @if($this->canManageCatalog)
                        <div class="flex flex-wrap items-center justify-start md:justify-end gap-2">
                            <x-button label="Guardar" icon="o-check" class="btn-primary" wire:click="save"
                                spinner="save" />

                            @if($this->hasUnsavedChanges())
                                <x-button label="Descartar" icon="o-arrow-uturn-left" class="btn-ghost btn-sm"
                                    wire:click="discardChanges" spinner="discardChanges"
                                    wire:confirm="¿Descartar los cambios sin guardar?" />
                                <span class="badge badge-warning badge-sm">Cambios sin guardar</span>
                            @endif
                        </div>
                    @else
                        <div class="flex flex-wrap items-center justify-between md:justify-end gap-2">
                            <div class="flex items-center gap-1.5">
                                <span class="badge badge-neutral text-xs font-semibold">
                                    {{ $this->selectedCount }} {{ $this->selectedCount === 1 ? 'materia' : 'materias' }}
                                </span>
                                @if($this->hasUnsavedChanges())
                                    <span class="badge badge-warning badge-sm" title="Cambios sin guardar">Sin guardar</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if($pdfExists)
                                    <x-button label="PDF" link="{{ route('inscriptions.pdf', $pdfFileName) }}" external
                                        icon="o-document-text" class="btn-outline btn-info btn-sm" target="_blank" />
                                @endif

                                <x-button label="Guardar" icon="o-bookmark" class="btn-outline btn-sm"
                                    wire:click="save" spinner="save" />

                                <x-dropdown label="Confirmar" icon="o-check-badge" class="btn-info btn-sm" right>
                                    <div class="p-3 text-xs max-w-72 space-y-2 bg-info/10 text-base-content rounded-lg border border-info/30 mb-1">
                                        <div class="flex items-center gap-1.5 text-info font-bold text-sm">
                                            <x-icon name="o-information-circle" class="w-4 h-4 shrink-0" />
                                            <span>Confirmar Inscripción</span>
                                        </div>
                                        <p class="text-base-content/80 leading-relaxed">
                                            Se generará el comprobante oficial en PDF con las <strong class="text-info font-bold">{{ $this->selectedCount }}</strong> {{ $this->selectedCount === 1 ? 'materia' : 'materias' }}.
                                        </p>
                                        @if($this->hasUnsavedChanges())
                                            <p class="text-warning text-[11px] font-medium flex items-center gap-1 pt-1.5 border-t border-info/20">
                                                <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 shrink-0" />
                                                Hay cambios pendientes que se guardarán.
                                            </p>
                                        @endif
                                    </div>
                                    <x-menu-item title="Confirmar y Generar PDF" icon="o-document-arrow-down"
                                        class="bg-info text-info-content hover:bg-info/90 font-medium rounded-lg justify-center py-2"
                                        wire:click="saveAndConfirm" spinner="saveAndConfirm" />
                                </x-dropdown>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="md:col-span-5">
                    <x-alert title="Se ha encontrado un error. Verifique con Tesorería" icon="o-exclamation-triangle"
                        class="alert-error" shadow />
                </div>
            @endif
        </div>

        @if($this->canManageCatalog && $this->targetUser->enabled)
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-base-content/70 py-2">
                <span>{{ $this->catalogSummary['with'] }} de {{ $this->catalogSummary['total'] }} materias con opciones</span>
                <span>{{ $this->catalogSummary['without'] }} sin opciones</span>
                <span class="text-base-content/50">Use coma para separar opciones cuando el tipo es múltiple.</span>
            </div>
        @endif

        @if($items->isEmpty())
            <x-alert icon="o-information-circle" class="alert-info my-4"
                title="{{ $this->canManageCatalog
                    ? 'No se encontraron materias para esta carrera.'
                    : 'No hay materias con inscripción disponible para esta carrera en este período.' }}"
                description="{{ $this->canManageCatalog
                    ? 'Verifique la carrera seleccionada.'
                    : 'Consulte con la institución si cree que es un error.' }}" />
        @else
            <div class="z-10">
                <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped>
                    @scope('cell_type', $item)
                        @if($this->canManageCatalog)
                            <x-select wire:model="rows.{{ $item->id }}.type" :options="$this->types" option-value="id"
                                option-label="name" class="select-sm w-full min-w-40" />
                            @if($this->getErrorBag()->has('rows.'.$item->id.'.type'))
                                <span class="text-error text-xs">{{ $this->getErrorBag()->first('rows.'.$item->id.'.type') }}</span>
                            @endif
                        @else
                            <x-badge :value="$item->type === 'csv-n' ? 'Múltiple' : 'Única'"
                                class="badge-ghost badge-sm text-[11px]" />
                        @endif
                    @endscope

                    @scope('cell_value', $item)
                        @if($this->canManageCatalog)
                            <div>
                                <x-input :key="'value-'.$item->id" wire:model.live.debounce.500ms="rows.{{ $item->id }}.value"
                                    icon="o-cube"
                                    :placeholder="$item->type === 'csv-n' ? 'Ej: Presencial,Semipresencial' : 'Ej: 20/07'" />
                                @if($this->getErrorBag()->has('rows.'.$item->id.'.value'))
                                    <span class="text-error text-xs">{{ $this->getErrorBag()->first('rows.'.$item->id.'.value') }}</span>
                                @endif

                                @if($item->type === 'csv-n')
                                    @if(empty($item->preview))
                                        <p class="text-xs text-base-content/50 mt-1">
                                            Sin opciones: los estudiantes no verán esta materia.
                                        </p>
                                    @else
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            @foreach($item->preview as $option)
                                                <span class="badge badge-ghost badge-sm text-[11px]">{{ $option }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @else
                            @if(empty($item->options))
                                <span class="text-sm text-base-content/50">Sin opciones disponibles</span>
                            @else
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        @if($item->type === 'csv-n')
                                            <x-choices wire:model.live="rows.{{ $item->id }}.selected" :options="$item->options"
                                                :key="'choices-'.$item->id.'-multi'" />
                                        @else
                                            <x-choices wire:model.live="rows.{{ $item->id }}.selected" :options="$item->options"
                                                :key="'choices-'.$item->id.'-single'" single clearable />
                                        @endif
                                    </div>
                                    <x-button icon="o-trash" class="btn-ghost btn-sm text-error"
                                        wire:click="clearSelection({{ $item->id }})" tooltip="Limpiar" />
                                </div>
                                @if($this->getErrorBag()->has('rows.'.$item->id.'.selected'))
                                    <span class="text-error text-xs">{{ $this->getErrorBag()->first('rows.'.$item->id.'.selected') }}</span>
                                @endif
                                @if($this->getErrorBag()->has('rows.'.$item->id.'.selected.*'))
                                    <span class="text-error text-xs">{{ $this->getErrorBag()->first('rows.'.$item->id.'.selected.*') }}</span>
                                @endif
                            @endif
                        @endif
                    @endscope
                </x-table>
            </div>
        @endif
    </x-card>
</div>
