<?php

namespace App\Livewire\Careers;

use App\Models\Career;
use App\Traits\AuthorizesAccess;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use AuthorizesAccess, Toast;

    public array $data = [
        'id' => null,
        'name' => '',
        'resolution' => '',
        'allow_enrollments' => true,
        'allow_evaluations' => true,
    ];

    public function mount($id = null)
    {
        $this->authorizeStaff();

        if ($id !== null) {
            $career = Career::findOrFail($id);
            $this->data = $career->toArray();
        }
    }

    public function save()
    {
        $this->authorizeStaff();

        Career::updateOrCreate(['id' => $this->data['id']], $this->data);
        $this->success('Carrera guardada.');
        $this->redirect('/careers');
    }

    public function delete()
    {
        $this->authorizeStaff();

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
