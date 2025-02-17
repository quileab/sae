<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public bool $drawer = false;

    public array $data = [
        'id' => null,
        'name' => '',
        'resolution' => '',
        'allow_enrollments' => true,
        'allow_evaluations' => true
    ];

    public function mount($id=null) {
        if($id===null) {
            $id = session('career_id');
        }

        if ($id!==null) {
            $this->data = \App\Models\Career::find($id)->toArray();
        }
    }

    public function save() {
        $career = \App\Models\Career::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Carrera guardada.');
    }

    public function delete() {
        $item = \App\Models\Career::find($this->data['id']);
        $item->delete();
        $this->success('Eliminada');
        $this->redirect('/careers');
    }

}; ?>

<div>
    <x-card title="Carrera" shadow separator>
        <x-slot:menu>
            <x-button @click="$wire.drawer = true" responsive 
            icon="o-ellipsis-vertical"
            class="btn-ghost btn-circle btn-outline btn-sm" />
        </x-slot:menu>
    <x-form wire:submit="save">    
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <x-input label="ID" type="number" wire:model="data.id" />
            <x-input label="Resolución" type="text" wire:model="data.resolution" />
        </div>
        <x-input label="Carrera" type="text" wire:model="data.name" />
        <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
            <x-toggle label="Permitir inscripciones" wire:model="data.allow_enrollments" />
            <x-toggle label="Permitir evaluaciones" wire:model="data.allow_evaluations" />
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
            <x-dropdown label="⚠️ELIMINAR REGISTRO" class="btn-error w-full mt-1">
                <x-menu-item title="Confirmar" wire:click.stop="delete" spinner="delete" icon="o-trash" class="bg-red-600 text-white" />
            </x-dropdown>
        </x-slot:actions>
    </x-drawer>
</div>