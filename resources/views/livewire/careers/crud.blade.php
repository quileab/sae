<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

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

}; ?>

<div>
    <x-header title="Carrera" separator />
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
</div>