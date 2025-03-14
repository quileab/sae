<?php

use App\Models\User;
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

    public function mount($id = null)
    {
        $this->roles = \App\Models\Role::all();
        if ($id === null) {
            $id = session('user_id');
        }

        if ($id !== null) {
            $this->data = User::find($id)->toArray();
        }

        $this->careers = \App\Models\Career::where(
            ['allow_enrollments' => true, 'allow_evaluations' => true]
        )->get();
        $this->career_id = $this->careers->first()->id;
        $this->data['careers'] = User::find($id)->careers;
    }

    public function save()
    {
        User::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Usuario guardado.');
        $this->redirect('/users');
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
                    option-label="description" />
            </div>

            <x-slot:actions>
                <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>

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

    <!-- DRAWER -->
    <x-drawer wire:model="drawer" title="Acciones" right with-close-button separator with-close-button close-on-escape
        class="lg:w-1/3">
        <x-input inline label="Password" wire:model="newPassword" type="text" icon="o-key" error-field="newPassword">
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