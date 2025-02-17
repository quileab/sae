<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

// Create or Update User information
new class extends Component {
    use Toast;

    public bool $drawer = false;
    public $roles=[];
    public $data=[
        'id' => null,
        'name' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
        'firstname' => '',
        'lastname' => '',
        'phone' => '',
        'role' => 'student',
        'enabled' => true
    ];
    public $subjects=[];

    public function mount($id=null) {
        $this->roles=\App\Models\Role::all();
        if($id===null) {
            $id = session('user_id');
        }

        if ($id!==null) {
            $this->data = User::find($id)->toArray();
        }
    }

    public function save() {
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
        User::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Usuario guardado.');
        $this->redirect('/users');
    }
}; ?>

<div>
    <x-card title="Usuario" shadow separator>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive 
            icon="o-ellipsis-vertical"
            class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
    <x-form wire:submit="save"> 
        <div class="grid grid-cols-1 gap-2 md:grid-cols-3">
            <x-input label="ID" type="number" wire:model="data.id" />
            <div class="mt-8 mx-auto">
                <x-toggle label="Habilitado" hint="Permite Inscribirse a rendir" wire:model="data.enabled" class="toggle-success" />
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
            <x-select label="Rol" icon="o-user" :options="$roles" wire:model="data.role_id"
                option-label="description" />
        </div>

        <x-slot:actions>
            <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>
    </x-form> 
    </x-card>
    
    <!-- DRAWER -->
    <x-drawer wire:model="drawer" title="Acciones" right with-close-button 
        separator with-close-button close-on-escape
        class="lg:w-1/3">
        <x-input inline label="Password" wire:model="newPassword" type="text" icon="o-key" error-field="newPassword">
            <x-slot:append>
                <x-button label="Cambiar Clave" icon="o-check" class="btn-primary rounded-s-none" 
                    wire:click="changePassword" spinner="changePassword"/>
            </x-slot:append>
        </x-input>
        <x-slot:actions>
            <x-dropdown label="ELIMINAR REGISTRO" class="btn-error w-full mt-1">
                <x-menu-item title="Confirmar" wire:click.stop="delete" spinner="delete" icon="o-trash" class="bg-error text-white" />
            </x-dropdown>
        </x-slot:actions>
    </x-drawer>
</div>

