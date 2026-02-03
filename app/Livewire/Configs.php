<?php

namespace App\Livewire;

use App\Models\Configs as ConfigsModel;
use Livewire\Component;
use Mary\Traits\Toast;

class Configs extends Component
{
    use Toast;

    public $data = [];

    public $group = '';

    public function mount()
    {
        $this->data = ConfigsModel::orderBy('group')->get()->toArray();
        // convert data config type=bool value to true or false
        foreach ($this->data as $key => $config) {
            if ($config['type'] == 'bool') {
                $this->data[$key]['value'] = $config['value'] == 'true' ? true : false;
            }
        }
    }

    public function saveChange($id)
    {
        $record = $this->data[$id];
        $config = ConfigsModel::find($record['id']);
        // check type to convert to boolean
        if ($record['type'] == 'bool') {
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

    public function render()
    {
        return view('livewire.configs');
    }
}
