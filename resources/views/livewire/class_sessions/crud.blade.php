<?php

use App\Models\User;
use App\Models\ClassSession;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

// Create or Update User information
new class extends Component {
    use Toast;

    public bool $drawer = false;
    public $roles = [];
    public $data = [
        'id' => null,
        'subject_id' => null,
        'date' => '',
        'teacher_id' => null,
        'class_number' => 1,
        'unit' => 1,
        'type' => '',
        'content' => '',
        'activities' => '',
        'observations' => ''
    ];
    public $user;
    public $subject;
    public $subjects = [];

    public function mount($id = null)
    {
        $user_id = session('user_id') ?? auth()->user()->id;

        $this->user = User::find($user_id);
        $this->subjects = $this->user->subjects;
        if ($id !== null) {
            $class_session = ClassSession::find($id);
            // check if class session subject_id is in this->subjects
            if ($this->subjects->contains($class_session->subject_id)) {
                $this->data = $class_session->toArray();
            } else {
                $this->redirect('/class-sessions');
            }
        } else {
            // new record
            $this->data['teacher_id'] = $user_id;
            $subject = \App\Models\Subject::find(session('subject_id'));
            $this->data['subject_id'] = $subject->id;
            // set date to string now 
            $this->data['date'] = date('Y-m-d');
            $this->data['class_number'] = \App\Models\ClassSession::where('subject_id', $subject->id)->max('class_number') + 1;
        }
    }

    public function save()
    {
        // TODO: Validate data
        // $valid=$this->validate(
        //     [
        //         'data.id' => 'nullable|integer',
        //         'data.name' => 'required',
        //         'data.email' => 'required|email|unique:users',
        //         'data.password' => 'required',
        //         'data.firstname' => 'required',
        //         'data.lastname' => 'required',
        //         'data.phone' => 'required',
        //         'data.role_id' => 'required',
        //     ]
        // );
        ClassSession::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Registro guardado');
        $this->redirect('/class-sessions');
    }
}; ?>

<div>
    <x-card title="Clase {{ $data['id']!==null ? $data['id'] : 'Nueva' }}" shadow separator>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive icon="o-ellipsis-vertical"
                class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
        <x-form wire:submit="save" no-separator>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <x-input label="Fecha" type="date" wire:model="data.date" />
                <x-input label="Clase #" type="number" wire:model="data.class_number" />
                <x-input label="Unidad" type="number" wire:model="data.unit"
                    hint="Utilice 0 para indicar que no hubo clase" />
                <x-input label="Tipo" type="text" wire:model="data.type" list="types" />
                <datalist id="types">
                    <option value="Expositivo"></option>
                    <option value="Teórico"></option>
                    <option value="Práctico"></option>
                    <option value="Teórico-Práctico"></option>
                    <option value="Evaluativo"></option>
                    <option value="Introductorio"></option>
                </datalist>
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-textarea label="Contenido" wire:model="data.content" rows="6" />
                <x-textarea label="Actividades" wire:model="data.activities" rows="6" />
            </div>
            <div class="grid grid-cols-1 gap-2">
                <x-textarea label="Observaciones" wire:model="data.observations" rows="2" />
            </div>

            <x-slot:actions>
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>

    <!-- DRAWER -->
    <x-drawer wire:model="drawer" title="Acciones" right with-close-button separator with-close-button close-on-escape
        class="lg:w-1/3">

        <x-slot:actions>
            <x-dropdown label="ELIMINAR REGISTRO" class="btn-error w-full mt-1">
                <x-menu-item title="Confirmar" wire:click.stop="delete" spinner="delete" icon="o-trash"
                    class="bg-error text-white" />
            </x-dropdown>
        </x-slot:actions>
    </x-drawer>
</div>