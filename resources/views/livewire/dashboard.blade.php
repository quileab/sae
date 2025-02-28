<?php

use Livewire\Volt\Component;

new class extends Component {
    public $cycleYear = null;

    public function mount(): void
    {
        // if session cycle is set, use it
        if (session()->has('cycle_id')) {
            $this->cycleYear = session('cycle_id');
        } else {
            // if not, set it to the current year
            $this->cycleYear = date('Y');
            session()->put('cycle_id', $this->cycleYear);
            session()->put('cycle_name', $this->cycleYear);

        }
    }

    public function saveCycleYear(): void
    {
        // session()->put('cycle', $this->cycleYear);
        // session()->put('cycle_name', $this->cycleYear);
        $id = $this->cycleYear;
        // emit change
        $this->dispatch('bookmarked', ['type' => 'cycle_id', 'value' => $id]);
    }
}; ?>

<div>
    <x-header title="Dashboard" separator />
    {{-- Select Cycle Year --}}
    <x-input label="Año lectivo" wire:model="cycleYear" icon="o-calendar" type="number" min="2023" max="2030" step="1">
        <x-slot:append>
            {{-- Add `rounded-s-none` class (RTL support) --}}
            <x-button label="Guardar" icon="o-check" class="btn-primary rounded-s-none" wire:click="saveCycleYear" />
        </x-slot:append>
    </x-input>
</div>