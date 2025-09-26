<?php

use App\Models\User;
//use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

// Create or Update User information
new class extends Component {
    use Toast;

    public bool $drawer = false;
    public $roles = [];
    public $data = [
        'id' => null,
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
        'firstname' => '',
        'lastname' => '',
        'phone' => '',
        'role' => 'student',
        'enabled' => true,
        'careers' => []
    ];
    //public $subjects = [];
    public $careers = [];
    public $career_id = null;
    public $user_id = null;

    public function mount($id = null)
    {
        $this->roles = \App\Models\User::$roles;
        $this->careers = \App\Models\Career::where(
            ['allow_enrollments' => true, 'allow_evaluations' => true]
        )->get();
        $this->career_id = $this->careers->first()->id;

        if ($id === null) {
            $id = session('user_id');
        }
        $this->user_id = $id;

        if ($id !== null) {
            $this->data = User::find($id)->toArray();
            $this->data['careers'] = User::find($id)->careers;
        }
    }

    public function save()
    {
        $data = [];
        //check if user not exists assign password
        if (!User::find($this->user_id)) {
            $data['password'] = $this->data['id'];
        }
        $data['id'] = $this->data['id'];
        $data['name'] = $this->data['name'];
        $data['email'] = $this->data['email'];
        $data['firstname'] = $this->data['firstname'];
        $data['lastname'] = $this->data['lastname'];
        $data['phone'] = $this->data['phone'];
        $data['role'] = $this->data['role'];
        $data['enabled'] = $this->data['enabled'];
        //dd($data, $this->user_id);
        User::updateOrCreate(['id' => $this->user_id], $data);
        $this->success('Usuario guardado.');
        //$this->redirect('/users');
    }

    public function changePassword()
    {
        $user = User::find($this->data['id']);
        $user->password = $this->data['password'];
        $user->save();
        $this->success('Contraseña cambiada.');
        $this->drawer = false;
        $this->skipRender();
    }

    public function assignCareer()
    {
        // add to User's careers
        $user = User::find($this->data['id']);
        // add to career_user pivot table
        $user->careers()->attach($this->career_id);
        $this->data['careers'] = $user->careers;
        $this->skipMount();
    }

    public function removeCareer($career_id)
    {
        // remove from User's careers
        $user = User::find($this->data['id']);
        // remove from career_user pivot table
        $user->careers()->detach($career_id);
        $this->data['careers'] = $user->careers;
        $this->skipMount();
    }

}; ?>

<div>
    <x-card title="Usuario" shadow separator>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive icon="o-ellipsis-vertical"
                class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
        <x-form wire:submit.prevent="save" no-separator>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <x-input label="ID" type="number" wire:model="data.id" />
                <div class="mt-8 mx-auto">
                    <x-toggle label="Habilitado" hint="Permite realizar inscripciones" wire:model="data.enabled"
                        class="toggle-success" />
                </div>
                <x-input label="ID Name" type="text" wire:model="data.name" />
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                <x-input label="Apellido" type="text" wire:model="data.lastname" />
                <x-input label="Nombres" type="text" wire:model="data.firstname" />
            </div>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
                <x-input label="E-mail" type="email" wire:model="data.email" />
                <x-input label="Teléfono" type="tel" wire:model="data.phone" />
                <x-select label="Rol" icon="o-user" :options="$roles" wire:model="data.role" option-value="name"
                    option-label="alias" />
            </div>

            <x-slot:actions>
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
        {{ session('user_id') }}
    </x-card>

    @if(!empty($data['id']))
        <x-card title="Carreras asignadas" shadow class="mt-2">
            <div class="bg-white/10 dark:bg-black/10 p-4 rounded-md mb-2">
                @foreach ($data['careers'] as $career)
                    <x-dropdown label="{{ $career->name }}" class="btn-primary">
                        {{-- para click wire:click.stop='action' --}}
                        <x-menu-item title="BORRAR" icon="o-trash" class="bg-error"
                            wire:click.stop="removeCareer({{ $career->id }})" />
                    </x-dropdown>
                @endforeach
            </div>

            <x-form wire:submit.prevent="assignCareer" no-separator>
                <x-select label="Carrera Disponibles" icon="o-academic-cap" :options="$careers" wire:model.lazy="career_id">
                    <x-slot:append>
                        <x-button label="Asignar" icon="o-plus" class="rounded-s-none btn-primary" type="submit"
                            spinner="assignCareer" />
                    </x-slot:append>
                </x-select>
            </x-form>
        </x-card>
    @endif

    <!-- DRAWER -->
    <x-drawer wire:model="drawer" title="Acciones" right with-close-button separator with-close-button close-on-escape
        class="lg:w-1/3">
        <x-input inline label="Password" wire:model="data.password" type="text" icon="o-key" error-field="newPassword">
            <x-slot:append>
                <x-button label="Cambiar Clave" icon="o-check" class="btn-primary rounded-s-none"
                    wire:click="changePassword" spinner="changePassword" />
            </x-slot:append>
        </x-input>
        <x-slot:actions>
            <x-dropdown label="ELIMINAR REGISTRO" class="btn-error w-full mt-1">
                <x-menu-item title="Confirmar" wire:click.stop="delete" spinner="delete" icon="o-trash"
                    class="bg-error text-white" />
            </x-dropdown>
        </x-slot:actions>
    </x-drawer>
</div>