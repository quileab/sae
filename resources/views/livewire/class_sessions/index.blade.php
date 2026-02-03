<div wire:init="items">
    <!-- HEADER -->
    <x-header title="Libro de Temas">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>
    <!-- TABLE  -->
    <x-card>
        <div class="flex justify-between items-center">
            @if($subjects->count() == 0)
                <x-alert title="Sin materias" description="No tienes materias asignadas." icon="o-exclamation-triangle"
                    class="alert-warning" />
            @else
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <x-select label="Materia" icon="o-queue-list" :options="$subjects" option-label="fullname"
                        wire:model.lazy="subject_id" inline />
                    <div class="text-right">
                        <x-button label="LISTA {{ $subject_id }}" icon="o-document-text" class="btn-primary"
                            link="/printClassbooks/{{ $subject_id }}" external no-wire-navigate />
                        <x-button label="NUEVO" icon="o-plus" class="btn-success" link="/class-session" />
                    </div>
                </div>
            @endif
        </div>

        <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped link="/class-session/{id}">
            @scope('cell_date', $item)
            {{ \Carbon\Carbon::parse($item->date)->format('d/m/Y') }}
            @endscope
            @scope('cell_students', $item)
            <x-button label="Reg." icon="o-user-group" link="/class-sessions/students/{{ $item->id }}"
                class="btn-primary btn-sm" />
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">
        <div class="grid grid-cols-2 gap-2">
            <x-button label="Nuevo Tema" icon="o-plus" class="btn-success" link="/class-session" />
            @if($subject_id)
                <x-button label="Administrar Contenidos" icon="o-book-open"
                    link="{{ route('subjects.content-manager', ['subject' => $subject_id]) }}" class="btn-primary" />
            @endif
            <x-button label="Asistencia" icon="o-document-text" class="btn-sm btn-info"
                link="/print/student-attendance-report/{{ $subject_id }}" external no-wire-navigate />
            <x-button label="Calificaciones" icon="o-academic-cap" class="btn-sm btn-info"
                link="/print/student-grades-report/{{ $subject_id }}" external no-wire-navigate />
        </div>
    </x-drawer>
</div>