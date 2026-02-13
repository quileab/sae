<?php

namespace App\Livewire\Subjects;

use App\Models\Career;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Mary\Traits\Toast;

class TableCrud extends Component
{
    use Toast;

    public $career_id;

    public $careers;

    public $subjects = [];

    public function mount()
    {
        $this->careers = Career::all();
        $this->career_id = session('career_id') ?? ($this->careers->first()->id ?? null);
        $this->loadSubjects();
    }

    public function updatedCareerId()
    {
        session(['career_id' => $this->career_id]);
        $this->loadSubjects();
    }

    public function loadSubjects()
    {
        if ($this->career_id) {
            $this->subjects = Subject::where('career_id', $this->career_id)
                ->orderBy('id')
                ->get()
                ->map(function ($subject) {
                    return [
                        'original_id' => $subject->id,
                        'id' => $subject->id,
                        'name' => $subject->name,
                    ];
                })
                ->toArray();
        } else {
            $this->subjects = [];
        }
    }

    public function save($index)
    {
        $data = $this->subjects[$index];

        try {
            DB::transaction(function () use ($data) {
                // If ID changed, handle cascade
                if ($data['id'] != $data['original_id']) {
                    if (Subject::where('id', $data['id'])->exists()) {
                        throw new \Exception("El ID {$data['id']} ya existe.");
                    }

                    DB::table('subjects')
                        ->where('id', $data['original_id'])
                        ->update(['id' => $data['id']]);
                }

                Subject::where('id', $data['id'])->update([
                    'name' => $data['name'],
                ]);
            });

            $this->subjects[$index]['original_id'] = $data['id'];
            $this->success('Materia actualizada.');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public function delete($index)
    {
        $id = $this->subjects[$index]['original_id'];
        Subject::where('id', $id)->delete();
        $this->success('Materia eliminada.');
        $this->loadSubjects();
    }

    public function add()
    {
        if (! $this->career_id) {
            $this->error('Seleccione una carrera primero.');

            return;
        }

        // Calculate next ID based on the maximum ID for this career
        $lastId = Subject::where('career_id', $this->career_id)->max('id');
        $newId = $lastId ? $lastId + 1 : 1;

        $newSubject = Subject::create([
            'id' => $newId,
            'career_id' => $this->career_id,
            'name' => 'Nueva Materia',
            'prerequisite' => '/',
        ]);

        $this->loadSubjects();
        $this->success('Materia creada con ID '.$newId);
    }

    public function render()
    {
        return view('livewire.subjects.table-crud');
    }
}
