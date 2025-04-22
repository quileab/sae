<?php

use Livewire\Volt\Component;
use Mary\Traits\Toast;

new class extends Component {

    use Toast;

    public $data = [];
    public $group = '';

    public function mount()
    {
        $this->data = App\Models\Configs::orderBy('group')->get()->toArray();
        // convert data config type=bool value to true or false
        foreach ($this->data as $key => $config) {
            if($config['type'] == 'bool') {
                $this->data[$key]['value'] = $config['value'] == 'true' ? true : false;
            }
        }
        //dd($this->data);
    }

    public function saveChange($id)
    {
        $record = $this->data[$id];
        $config = App\Models\Configs::find($record['id']);
        // check type to convert to boolean
        if($record['type'] == 'bool') {
            $record['value'] = $record['value'] ? 'true' : 'false';
        }
        $config->value = $record['value'];
        try {
            $config->save();
            $this->success('Configuración guardada.');
        } catch (\Exception $e) {
            $this->error('Error al guardar la configuración.');
        }
    }

}; ?>

<div class="grid gap-4">
    @foreach ($data as $key => $config)
        @if($config['group'] != $group)
            @php $group = $config['group']; @endphp
            <h2 class="text-2xl font-semibold uppercase">{{ $config['group'] }}</h2>
        @endif
        <x-form wire:submit='saveChange({{ $key }})' id="form{{ $key }}" 
        class="ml-4 p-4 rounded-md">
            @switch($config['type'])
                @case('text')
                    <x-input label="{{ $config['description'] }}" hint="{{ $config['id'] }}" wire:model="data.{{ $key }}.value">
                        <x-slot:append>
                            <x-button label="Guardar" form="form{{ $key }}" type="submit" icon="o-check" class="btn-primary rounded-s-none" /> 
                        </x-slot:append>
                    </x-input>                            
                @break
                @case('bool')
                <div class="flex border border-primary rounded-lg align-middle items-center pl-4 justify-between">
                    <x-checkbox label="{{ $config['description'] }}" wire:model="data.{{ $key }}.value" />
                    <x-button label="Guardar" form="form{{ $key }}" type="submit" icon="o-check" class="btn-primary rounded-s-none" /> 
                </div>
                    <p class="text-sm">{{ $config['id'] }}</p>
                    @break
                @default
                    <p class="text-lg bg-warning">Error</p>
            @endswitch
        </x-form>
    @endforeach

</div>