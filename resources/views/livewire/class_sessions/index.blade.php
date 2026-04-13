<div>
    <!-- HEADER -->
    <x-header title="Libro de Temas">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
    </x-header>
    <!-- TABLE  -->
    <x-card>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            @if($this->subjects->count() == 0)
                <x-alert title="Sin materias" description="No tienes materias asignadas." icon="o-exclamation-triangle"
                    class="alert-warning w-full" />
            @else
                <div class="flex-1 w-full max-w-md">
                    <x-select label="Materia" icon="o-queue-list" :options="$this->subjects" option-label="fullname"
                        wire:model.live="subject_id" inline />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="join">
                        <x-button label="Asistencia" icon="o-document-text" class="btn-sm btn-info btn-outline join-item"
                            link="/print/student-attendance-report/{{ $subject_id }}?cycle={{ $this->cycle }}" external no-wire-navigate />
                        <x-button label="Seguimiento" icon="o-academic-cap" class="btn-sm btn-info btn-outline join-item"
                            link="/print/student-grades-report/{{ $subject_id }}?cycle={{ $this->cycle }}" external no-wire-navigate />
                        <x-button label="Temas" icon="o-printer" class="btn-sm btn-info btn-outline join-item"
                            link="/printClassbooks/{{ $subject_id }}?cycle={{ $this->cycle }}" external no-wire-navigate />
                    </div>
                    
                    <x-button label="NUEVO TEMA" icon="o-plus" class="btn-sm btn-success" link="/class-session?subject_id={{ $subject_id }}" />
                </div>
            @endif
        </div>

        <x-table :headers="$headers" :rows="$this->items" :sort-by="$sortBy" striped link="/class-session/{id}?subject_id={{ $subject_id }}" class="mt-4">
            @scope('cell_date', $item)
            {{ \Carbon\Carbon::parse($item->date)->format('d/m/Y') }}
            @endscope
            @scope('cell_students', $item)
            <x-button label="Reg." icon="o-user-group" link="/class-sessions/students/{{ $item->id }}?subject_id={{ $this->subject_id }}"
                class="btn-primary btn-sm" />
            @endscope
        </x-table>
    </x-card>
</div>