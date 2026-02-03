<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Models\User;
use Livewire\Component;
use Mary\Traits\Toast;

class Enrollment extends Component
{
    use Toast;

    public $modal = false;

    public $careerId; // Carrera seleccionada

    public $careers;

    public $enrolledSubjects = []; // Materias en las que está matriculado

    public $subjects = []; // Todas las materias de la carrera seleccionada

    public $blocked = false;

    public function mount()
    {
        $user = $this->getUser();
        if (! $user) {
            $this->warning('No se ha seleccionado Usuario.');
            $this->modal = true;
        }
        $this->careers = $user->careers;

        if ($this->careers->isEmpty()) {
            $this->blocked = true;

            return;
        }

        $careerId = (session()->has('career_id')) ? session('career_id') : $this->careers->first()->id;

        $this->careerId = $careerId;

        // Cargar las materias de la carrera
        $this->loadSubjects();
        $this->loadEnrollments();
    }

    public function getUser()
    {
        if (session()->has('user_id')) {
            $user = User::find(session('user_id'));
        } else {
            $user = auth()->user();
            // save user to session
        }

        return $user;
    }

    public function loadSubjects()
    {
        $this->subjects = Subject::where('career_id', $this->careerId)->get();
    }

    public function loadEnrollments()
    {
        $user_id = $this->getUser()->id;
        $this->enrolledSubjects = \App\Models\Enrollment::where('user_id', $user_id)
            ->whereHas('subject', function ($query) {
                $query->where('career_id', $this->careerId);
            })
            ->pluck('subject_id') // Solo obtiene los IDs de las materias matriculadas
            ->toArray();
    }

    public function toggleEnrollment($subjectId)
    {
        $user_id = $this->getUser()->id;
        if (in_array($subjectId, $this->enrolledSubjects)) {
            // Si ya está matriculado, eliminar la inscripción
            \App\Models\Enrollment::where('user_id', $user_id)
                ->where('subject_id', $subjectId)
                ->delete();
            $this->enrolledSubjects = array_diff($this->enrolledSubjects, [$subjectId]);
        } else {
            // Si no está matriculado, agregar la inscripción
            \App\Models\Enrollment::create([
                'user_id' => $user_id,
                'subject_id' => $subjectId,
                'status' => 'active', // Por defecto
            ]);
            $this->enrolledSubjects[] = $subjectId;
        }
    }

    public function updated()
    {
        $this->loadSubjects();
        $this->loadEnrollments();
        $this->skipMount();
    }

    public function render()
    {
        return view('livewire.enrollment');
    }
}
