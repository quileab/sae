<?php

use Livewire\Volt\Component;

new class extends Component {
  public $dateFrom;
  public $dateTo;
  public $search = '';

  public function mount()
  {
    $this->dateFrom = now()->subDays(30)->format('Y-m-d');
    $this->dateTo = now()->format('Y-m-d');
  }
}; ?>

<div>
  <div class="flex flex-wrap gap-4 rounded bg-gray-300/10 shadow-md p-4">
    <x-input label="{{ __('Fecha Desde') }}" type='date' wire:model.debounce='dateFrom' inline />
    <x-input label="{{ __('Fecha Hasta') }}" type='date' wire:model.debounce='dateTo' inline />
    <x-input label="Buscar..." icon="o-magnifying-glass" placeholder="Buscar..." type="text"
      wire:model.debounce='search' inline />
    <div wire:loading.remove class="flex flex-wrap mx-3 justify-between">
      <a href="{{ route('printStudentsPayments', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo, 'search' => $search]) }}"
        target='_blank'>
        <x-button icon="o-list-bullet" label="Caja" class="btn-primary" />
      </a>
    </div>
  </div>
</div>