<?php

namespace App\Livewire\Subjects;

use App\Models\Career;
use App\Models\Subject;
use App\Traits\AuthorizesAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

class Crud extends Component
{
    use AuthorizesAccess, Toast;

    public $original_id = null;

    public array $data = [
        'id' => null,
        'career_id' => '',
        'name' => '',
        'prerequisite' => '',
    ];

    public $subjectsToStudy = [];

    public $subjectsToExam = [];

    public function mount($id = null)
    {
        $this->authorizeStaff();

        if ($id !== null) {
            $subject = Subject::findOrFail($id);
            $this->data = $subject->toArray();
            $this->original_id = $id;
        }

        $this->createPrequisite();
    }

    #[Computed]
    public function careers()
    {
        return Career::all();
    }

    #[Computed]
    public function subjects()
    {
        if ($this->data['career_id']) {
            return Subject::where('career_id', $this->data['career_id'])
                ->where('id', '!=', $this->data['id'])
                ->get();
        }

        return collect();
    }

    public function save()
    {
        $this->authorizeStaff();

        // If we are editing and the ID has changed
        if ($this->original_id && $this->data['id'] != $this->original_id) {
            if (Subject::where('id', $this->data['id'])->exists()) {
                $this->error('El nuevo ID ya está en uso por otra materia.');

                return;
            }

            DB::table('subjects')
                ->where('id', $this->original_id)
                ->update(['id' => $this->data['id']]);
        }

        Subject::updateOrCreate(['id' => $this->data['id']], $this->data);

        $this->success('Materia guardada.');
        $this->redirect('/subjects');
    }

    public function delete()
    {
        $this->authorizeStaff();

        $item = Subject::find($this->data['id']);
        if ($item) {
            $item->delete();
            $this->success('Registro Eliminado.');

            return redirect('/subjects');
        }
    }

    private function createPrequisite()
    {
        if (! empty($this->data['prerequisite']) && empty($this->subjectsToStudy) && empty($this->subjectsToExam)) {
            $prerequisite = explode('/', $this->data['prerequisite']);
            $this->subjectsToStudy = explode(' ', $prerequisite[0]);
            $this->subjectsToExam = isset($prerequisite[1]) ? explode(' ', $prerequisite[1]) : [];
        }

        sort($this->subjectsToStudy);
        sort($this->subjectsToExam);

        $this->data['prerequisite'] = implode(' ', $this->subjectsToStudy).'/'.implode(' ', $this->subjectsToExam);
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
