<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Mary\Traits\Toast;

// Create or Update User information
new class extends Component {
    use Toast;

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
        if($id===null) {
            $id = session('user_id');
        }

        if ($id!==null) {
            $this->data = \App\Models\User::find($id)->toArray();
        }
    }

    public function save() {
        $user = \App\Models\User::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Usuario guardado.');
        $this->redirect('/users');
    }
}; ?>

<div>
    
    <x-header title="Usuario" separator />
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
            <x-select label="Rol" icon="o-user" :options="User::$roles" wire:model="data.role" />
        </div>

        <x-slot:actions>
            <x-button label="Guardar" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>
    </x-form>  
</div>

