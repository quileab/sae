<?php

namespace App\Livewire\Subjects;

use App\Models\Career;
use App\Models\Subject;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use Toast;

    public $original_id = null;

    public array $data = [
        'id' => null,
        'career_id' => '',
        'name' => '',
        'prerequisite' => '',
    ];

    public $careers;

    public $subjects;

    public $subjectsToStudy = [];

    public $subjectsToExam = [];

    public function mount($id = null)
    {
        if ($id === null) {
            $id = session('subject_id');
        }

        if ($id !== null) {
            $subject = Subject::find($id);
            if ($subject) {
                $this->data = $subject->toArray();
                $this->original_id = $id;
            }
        }

        $this->careers = Career::all();
        $this->refreshSubjects();
        $this->createPrequisite();
    }

    public function updatedDataCareerId()
    {
        $this->refreshSubjects();
    }

    public function refreshSubjects()
    {
        if ($this->data['career_id']) {
            $this->subjects = Subject::where('career_id', $this->data['career_id'])
                ->where('id', '!=', $this->data['id'])
                ->get();
        } else {
            $this->subjects = collect();
        }
    }

    public function save()
    {
        // If we are editing and the ID has changed
        if ($this->original_id && $this->data['id'] != $this->original_id) {
            // Check if the new ID already exists to avoid conflict
            if (Subject::where('id', $this->data['id'])->exists()) {
                $this->error('El nuevo ID ya está en uso por otra materia.');

                return;
            }

            // Perform manual update of the ID directly in DB to trigger ON UPDATE CASCADE
            \Illuminate\Support\Facades\DB::table('subjects')
                ->where('id', $this->original_id)
                ->update(['id' => $this->data['id']]);
        }

        Subject::updateOrCreate(['id' => $this->data['id']], $this->data);

        $this->success('Materia guardada.');
        $this->redirect('/subjects');
    }

    public function delete()
    {
        $item = Subject::find($this->data['id']);
        if ($item) {
            $item->delete();
            $this->success('Registro Eliminado.');

            return redirect('/subjects');
        }
    }

    private function createPrequisite()
    {
        // if prerequisite not empty, and array is empty, create arrays
        if (! empty($this->data['prerequisite']) && empty($this->subjectsToStudy) && empty($this->subjectsToExam)) {
            // split prerequisite study/exam by '/'
            $prerequisite = explode('/', $this->data['prerequisite']);

            // split prerequisite study/exam by ' '
            $this->subjectsToStudy = explode(' ', $prerequisite[0]);
            $this->subjectsToExam = explode(' ', $prerequisite[1]);
        }

        // order by value subjectsStudy and subjectsToExam
        sort($this->subjectsToStudy);
        sort($this->subjectsToExam);
        // Create prequisite string from array of subjects
        $this->data['prerequisite'] =
            implode(' ', $this->subjectsToStudy).'/'.implode(' ', $this->subjectsToExam);
    }

    public function toggleSubjectTo($to, $subject_id)
    {
        if ($to == 'study') {
            if (in_array($subject_id, $this->subjectsToStudy)) {
                $this->subjectsToStudy = array_diff($this->subjectsToStudy, [$subject_id]);
            } else {
                $this->subjectsToStudy[] = $subject_id;
            }
        } else {
            if (in_array($subject_id, $this->subjectsToExam)) {
                $this->subjectsToExam = array_diff($this->subjectsToExam, [$subject_id]);
            } else {
                $this->subjectsToExam[] = $subject_id;
            }
        }
        $this->createPrequisite();
    }

    public function render()
    {
        return view('livewire.subjects.crud');
    }
}
