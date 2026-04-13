<?php

namespace App\Livewire\Subjects;

use App\Models\Career;
use App\Models\Subject;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class TableCrud extends Component
{
    use AuthorizesAccess, Toast;

    #[Url]
    public $career_id = null;

    public $subjects = [];

    public function mount()
    {
        $this->authorizeStaff();

        if (! $this->career_id && $this->careers->isNotEmpty()) {
            $this->career_id = $this->careers->first()->id;
        }

        $this->loadSubjects();
    }

    #[Computed]
    public function careers()
    {
        return Career::all();
    }

    public function updatedCareerId()
    {
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
        $this->authorizeStaff();

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
        $this->authorizeStaff();

        $id = $this->subjects[$index]['original_id'];
        Subject::where('id', $id)->delete();
        $this->success('Materia eliminada.');
        $this->loadSubjects();
    }

    public function add()
    {
        $this->authorizeStaff();

        if (! $this->career_id) {
            $this->error('Seleccione una carrera primero.');

            return;
        }

        $logicalStart = $this->career_id * 100;
        $maxInCareer = Subject::where('career_id', $this->career_id)->max('id');

        $newId = $maxInCareer ? $maxInCareer + 1 : $logicalStart + 1;

        while (Subject::where('id', $newId)->exists()) {
            $newId++;
        }

        Subject::create([
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
