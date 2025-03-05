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

    public $role_student = 'student';
    public $class_session;
    public $grades = [];
    public $data = [];

    public function mount($id = null): void
    {
        //$this->user = User::find(session('user_id')) ?: auth()->user();
        //$this->role_student = \App\Models\Role::where('name', 'student')->first()->name;
        if (session()->get('subject_id', false) == false) {
            $this->redirect('/class-sessions');
        }
        if ($id !== null) {
            $this->class_session = \App\Models\ClassSession::find($id);
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
            ['key' => 'grades_attendance', 'label' => 'Asistencia', 'sortable' => false],
        ];
    }

    public function items(): Collection
    {
        $search = Str::of($this->search)->lower()->ascii(); // Convertir la búsqueda a minúsculas y eliminar acentos
        // return Users enrolled in subjects
        $return = User::select(
            'users.id',
            'users.lastname',
            'users.firstname',
            'users.email',
            'users.phone'
        )
            ->withAggregate('grades', 'attendance')
            ->join('enrollments', 'users.id', '=', 'enrollments.user_id')
            ->where([
                ['enrollments.subject_id', '=', session('subject_id')],
                ['enrollments.status', '=', 'active'],
                ['users.role', '=', $this->role_student]
            ])
            ->where(function ($query) use ($search) {
                $query->where('users.lastname', 'like', "%$search%")
                    ->orWhere('users.firstname', 'like', "%$search%")
                    ->orWhere('users.email', 'like', "%$search%");
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get();
        //dd($return, $this->role_student);
        return $return;
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
        $this->drawer = true;
    }

    public function bookmark($id): void
    {
        // Notifica al componente Bookmarks
        $this->dispatch('bookmarked', ['type' => 'user_id', 'value' => $id]);
    }
}; ?>

<div>
    <!-- HEADER -->
    <x-header title="Estudiantes" separator progress-indicator>
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
                <x-button label="Asistencia" icon="o-user-circle" class="text-yellow-600 btn-ghost btn-sm"
                    wire:click="attendance({{ $item }})" />
                <x-dropdown>
                    <x-slot:trigger>
                        <x-button icon="o-chevron-down" class="btn-ghost btn-sm" />
                    </x-slot:trigger>

                    <x-menu-item title="Chat" icon="o-chat-bubble-left" class="text-yellow-600" />

                </x-dropdown>
            </div>
            @endscope
        </x-table>
    </x-card>

    <!-- FILTER DRAWER -->
    <x-drawer wire:model="drawer" title="Opciones" right separator with-close-button class="lg:w-1/3">
        {{-- Show data of current selected item: lastname firstname --}}
        <div class="flex items-center gap-4 text-lg text-primary">
            <div class="flex items-center text-lg">
                <x-icon name="o-user-circle" />
                {{ $data['lastname'] ?? '' }}, {{ $data['firstname'] ?? '' }}
            </div>
        </div>

        <x-input label="Asistencia" wire:model="grades.attendance" type="number" min="0" max="100" inline
            class="w-full" />
        <div class="grid grid-cols-3 items-center gap-4 mt-4">
            <x-button label="Ausente" icon="o-x-mark" class="btn-outline" wire:click="$set('grades.attendance', 0)" />
            <x-button label="50" icon="o-check" class="btn-outline" wire:click="$set('grades.attendance', 50)" />
            <x-button label="100" icon="o-check" class="btn-outline" wire:click="$set('grades.attendance', 100)" />
        </div>
        <div class="grid grid-cols-2 items-center gap-4 mt-4">
            <x-input label="Calificación" wire:model="grades.grade" type="number" min="0" max="100" inline />
            <x-checkbox label="Aprueba" wire:model="grades.approved" hint="Es automático" readonly />
        </div>
        <div class="grid items-center gap-4 mt-4">
            <x-input label="Observaciones" wire:model="grades.comments" type="text" inline class="w-full" />
        </div>
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.drawer = false" />
        </x-slot:actions>
    </x-drawer>
</div>