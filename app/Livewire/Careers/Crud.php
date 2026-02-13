<?php

namespace App\Livewire\Careers;

use App\Models\Career;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use Toast;

    public array $data = [
        'id' => null,
        'name' => '',
        'resolution' => '',
        'allow_enrollments' => true,
        'allow_evaluations' => true,
    ];

    public function mount($id = null)
    {
        if ($id === null) {
            $id = session('career_id');
        }

        if ($id !== null) {
            $career = Career::find($id);
            if ($career) {
                $this->data = $career->toArray();
            }
        }
    }

    public function save()
    {
        Career::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Carrera guardada.');
        $this->redirect('/careers');
    }

    public function delete()
    {
        $item = Career::find($this->data['id']);
        if ($item) {
            $item->delete();
            $this->success('Eliminada');
            $this->redirect('/careers');
        }
    }

    public function render()
    {
        return view('livewire.careers.crud');
    }
}
