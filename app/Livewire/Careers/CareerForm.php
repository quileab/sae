<?php

namespace App\Livewire\Careers;

use App\Models\Career;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class CareerForm extends Component
{
    use AuthorizesAccess, Toast;

    public $original_id = null;

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
            $this->original_id = $id;
        }
    }

    public function save()
    {
        $this->authorizeStaff();

        $this->validate([
            'data.id' => 'required|integer|unique:careers,id,'.($this->original_id ?? 'NULL'),
            'data.name' => 'required|string|max:255',
            'data.resolution' => 'nullable|string|max:255',
            'data.allow_enrollments' => 'required|boolean',
            'data.allow_evaluations' => 'required|boolean',
        ], [
            'data.id.required' => 'El ID de carrera es obligatorio.',
            'data.id.integer' => 'El ID de carrera debe ser un número entero.',
            'data.id.unique' => 'El ID de carrera ya está en uso.',
            'data.name.required' => 'El nombre de la carrera es obligatorio.',
        ]);

        if ($this->original_id && $this->data['id'] != $this->original_id) {
            if (Career::where('id', $this->data['id'])->exists()) {
                $this->error('El nuevo ID ya está en uso por otra carrera.');

                return;
            }

            DB::table('careers')
                ->where('id', $this->original_id)
                ->update(['id' => $this->data['id']]);
        }

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
        return view('livewire.careers.career-form');
    }
}
