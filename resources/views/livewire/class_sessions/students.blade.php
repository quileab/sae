<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $search = '';
    public string $filterRole = '';
    public bool $drawer = false;
    public array $sortBy = ['column' => 'lastname', 'direction' => 'asc'];

    private $role_student = 'student';
    public $class_session;
    public $grades = [];
    public $data = [];

    public function mount($id = null)
    {
        // check if user is logged in and has teacher role
        if (!auth()->user()->hasAnyRole(['teacher', 'admin', 'principal', 'administrative'])) {
            return redirect()->back();
        }

        //$this->user = User::find(session('user_id')) ?: auth()->user();
        //$this->role_student = \App\Models\Role::where('name', 'student')->first()->name;
        if (session()->get('subject_id', false) == false) {
            $this->redirect('/class-sessions');
        }
        if ($id !== null) {
            $this->class_session = \App\Models\ClassSession::find($id);
        } else {
            $this->class_session = new \App\Models\ClassSession();
            $this->class_session->id = null;
            $this->class_session->subject_id = session('subject_id');
            $this->class_session->teacher_id = session('user_id');
            $this->class_session->date = now();
            $this->class_session->class_number = 0;
            $this->class_session->unit = '';
            $this->class_session->content = '';
        }
        //check if class session subject_id belongs to this user
        if (
            $this->class_session->subject_id != session('subject_id') ||
            auth()->user()->hasSubject(session('subject_id') == false)
        ) {
            $this->redirect('/class-sessions');
        }
    }

    // Delete action
    public function delete($id): void
    {
        $this->warning("Will delete #$id", 'It is fake.', position: 'toast-bottom');
    }

    // Table headers
    public function headers(): array
    {
        return [
            ['key' => 'row_id', 'label' => '#', 'class' => 'w-10'],
            ['key' => 'lastname', 'label' => 'Apellido', 'class' => 'w-64'],
            ['key' => 'firstname', 'label' => 'Nombre', 'class' => 'w-full'],
            ['key' => 'attendance', 'label' => 'Asistencia', 'sortable' => false],
        ];
    }

    public function items(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos
        $subjectId = session('subject_id');

        // return collection of users enrolled in the subject and their grades filtered by search and ordered by column
        $query = User::query()
            ->select('users.id', 'users.lastname', 'users.firstname', 'users.email', 'users.phone')
            ->leftJoin('enrollments', 'users.id', '=', 'enrollments.user_id')
            ->leftJoin('grades', function ($join) {
                $join->on('users.id', '=', 'grades.user_id')
                    ->where('grades.class_session_id', '=', $this->class_session->id);
            })
            ->where('enrollments.subject_id', $subjectId)
            ->where('enrollments.status', 'active')
            ->where('users.role', $this->role_student)

            // ->where('users.lastname', 'like', "%{$search}%")
            // ->orWhere('users.firstname', 'like', "%{$search}%")
            // ->orWhere('users.email', 'like', "%{$search}%")

            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->addSelect('grades.grade as grade', 'grades.attendance as attendance');
        //dd($query->get()->toArray());
        return $query->get();
    }

    public function with(): array
    {
        return [
            'items' => $this->items(),
            'headers' => $this->headers()
        ];
    }

    public function attendance($item): void
    {
        if (isset($this->class_session->id) == false) {
            $this->error('No se ha seleccionado una clase.');
            return;
        }
        $this->data = $item;
        try {
            $this->grades = \App\Models\Grade::where('user_id', $item['id'])
                ->where('class_session_id', $this->class_session->id)
                ->first()->toArray();
        } catch (\Throwable $th) {
            $this->grades = [
                'user_id' => $item['id'],
                'class_session_id' => $this->class_session->id,
                'attendance' => 0,
                'grade' => 0,
                'approved' => 0,
                'comments' => ''
            ];
        }
        //$this->grades['approved'] = $this->grades['grade'] >= 6 ? true : false;
        $this->drawer = true;
    }

    public function attendanceSet($item, $value): void
    {
        $this->attendance($item);
        $this->saveGrade($value);
    }

    public function saveGrade($value = null): void
    {
        if ($value !== null) {
            $this->grades['attendance'] = $value;
        }
        $this->validate([
            'grades.attendance' => ['required', 'integer', 'min:0', 'max:100'],
            'grades.grade' => ['required', 'integer', 'min:0', 'max:10'],
            'grades.comments' => ['nullable', 'string', 'max:255']
        ]);

        \App\Models\Grade::updateOrCreate(
            ['user_id' => $this->data['id'], 'class_session_id' => $this->class_session->id],
            [
                'user_id' => $this->data['id'],
                'class_session_id' => $this->class_session->id,
                'attendance' => $this->grades['attendance'],
                'grade' => $this->grades['grade'],
                'approved' => $this->grades['approved'],
                'comments' => $this->grades['comments']
            ]
        );
        $this->drawer = false;
        $this->success('Registrado.');
    }

    public function bookmark($id): void
    {
        // Notifica al componente Bookmarks
        $this->dispatch('bookmarked', ['type' => 'user_id', 'value' => $id]);
    }

    public function deregister(): void
    {
        // Desmatricula al estudiante de la materia
        \App\Models\Enrollment::where('user_id', $this->data['id'])
            ->where('subject_id', session('subject_id'))
            ->delete();
        $this->success('Estudiante desmatriculado.');
        $this->drawer = false;
    }


}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Estudiantes">
        <x-slot:middle class="!justify-end">
            <x-input placeholder="buscar..." wire:model.live.debounce="search" clearable icon="o-magnifying-glass" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="OPCIONES" @click="$wire.drawer = true" responsive icon="o-bars-3" />
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-card>
        {{-- class session data --}}
        <div class="flex items-center  text-lg text-primary">
            <x-icon name="o-calendar" class="text-warning" />
            <span class="mx-2">{{ Carbon\Carbon::parse($class_session->date ?? now())->format('d/m/Y') ?? '-' }}</span>
            <x-icon name="o-cube" class="text-warning" />
            <span class="mx-2">{{ $class_session->class_number ?? '-' }} » {{ $class_session->unit ?? '-' }}</span>
            <x-icon name="o-academic-cap" class="text-warning" />
            <span class="mx-2">{{ $class_session->content ?? 'CLASE INEXISTENTE' }}</span>
        </div>

        <x-table :headers="$headers" :rows="$items" :sort-by="$sortBy" striped>
            {{-- loop index --}}
            @scope('cell_row_id', $item)
            {{ $loop->index + 1 }}
            @endscope

            {{-- actions --}}
            @scope('actions', $item)
            <div class="flex items-center align-middle mr-4 gap-2">
                <x-button label="100" icon="o-percent-badge" class="text-success btn-ghost btn-sm"
                    wire:click="attendanceSet({{ $item }}, 100)" />
                <x-button label="Registro" icon="o-user-circle" class="text-yellow-600 btn-ghost btn-sm"
                    wire:click="attendance({{ $item }})" />
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button icon="o-chevron-down" class="btn-ghost btn-sm" />
                    </x-slot:trigger>

                    <x-button label="LISTA" icon="o-document-text" class="btn-primary"
                        link="/printClassbooks/subject/{{ $item->id }}" external no-wire-navigate />
                    <x-menu-item title="Chat" icon="o-chat-bubble-left" class="text-yellow-600" />

                </x-dropdown>
            </div>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right with-close-button class="lg:w-1/3">
        {{-- Show data of current selected item: lastname firstname --}}
        <div class="flex items-center gap-4 text-lg">
            <div class="flex items-center text-lg mb-4">
                <x-icon name="o-user-circle" />
                {{ $data['lastname'] ?? '' }}, {{ $data['firstname'] ?? '' }}
            </div>
        </div>

        <x-input label="Asistencia" wire:model="grades.attendance" type="number" min="0" max="100" inline
            class="w-full" />
        <div class="grid grid-cols-3 items-center gap-4 mt-2">
            <x-button label="Ausente" icon="o-x-mark" class="btn-error btn-outline btn-sm"
                wire:click="$set('grades.attendance', 0)" />
            <x-button label="50" icon="o-check" class="btn-warning btn-outline btn-sm"
                wire:click="$set('grades.attendance', 50)" />
            <x-button label="100" icon="o-check" class="btn-success btn-outline btn-sm"
                wire:click="$set('grades.attendance', 100)" />
        </div>
        <div class="flex items-center gap-4 mt-4">
            <x-input label="Calificación" wire:model="grades.grade" type="number" min="0" max="100" class="w-24"
                inline />
            <x-checkbox label="Aprueba" wire:model="grades.approved" hint="Notas no numéricas" />
        </div>
        <div class="grid items-center gap-4 mt-4">
            <x-input label="Observaciones" wire:model="grades.comments" type="text" placeholder="Observaciones" hint="Comience con Ev: o TP: para indicar el TIPO (Evaluación o Trabajo Practico), de esta manera el sistema podrá calcular el promedio de notas" class="w-full" />
        </div>
        <x-slot:actions>
            <x-dropdown>
                <x-slot:trigger>
                    <x-button label="Desmatricular" icon="o-exclamation-triangle" class="btn-warning" />
                </x-slot:trigger>
                <x-menu-item title="ACEPTAR" icon="o-user-minus" class="bg-error" wire:click="deregister()" />
            </x-dropdown>
            <x-button label="GUARDAR" icon="o-check" class="btn-primary" wire:click="saveGrade" spinner="saveGrade" />
        </x-slot:actions>
    </x-drawer>
</div>