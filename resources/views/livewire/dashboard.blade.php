<?php

use Livewire\Volt\Component;

new class extends Component {
    public $cycleYear = null;
    public $inscriptionsStatus = null;

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
        $this->inscriptionsStatus = \App\Models\Configs::where('group', 'inscriptions')->get();
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

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <x-card title="{{ config('app.name') }}" shadow-md>
        {{-- Select Cycle Year --}}
        <x-input label="Ciclo lectivo" wire:model="cycleYear" icon="o-calendar" type="number" min="2023" max="2030"
            step="1">
            <x-slot:append>
                {{-- Add `rounded-s-none` class (RTL support) --}}
                <x-button label="Guardar" icon="o-check" class="btn-primary rounded-s-none"
                    wire:click="saveCycleYear" />
            </x-slot:append>
        </x-input>
        
        @foreach ($inscriptionsStatus as $inscription)
        <div class="mt-2 grid grid-cols-1 border border-primary p-2 rounded-md
            {{ $inscription['value'] == 'true' ? 'bg-success/10' : 'bg-error/10' }}">
            <x-icon name="{{ $inscription['value'] == 'true' ? 'o-check' : 'o-x-mark' }}"
                class="{{ $inscription['value'] == 'true' ? 'text-success' : 'text-error' }}"
                label="{{ $inscription['description'] }}" />
        </div>  

        @endforeach
    </x-card>
</div>