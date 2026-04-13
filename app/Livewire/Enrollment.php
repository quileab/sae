<?php

namespace App\Livewire;

use App\Models\Subject;
use App\Traits\AuthorizesAccess;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

class Enrollment extends Component
{
    use AuthorizesAccess, Toast;

    public $modal = false;

    #[Url]
    public $careerId = null;

    #[Url]
    public $user_id = null;

    public $blocked = false;

    public $modalMessage = 'Haga click para ser redirigido';

    public function mount()
    {
        $user = $this->targetUser;

        if (! $user) {
            $this->user_id = request()->query('user_id');
            $this->modalMessage = 'Usuario no encontrado.';
            $this->modal = true;

            return;
        }

        if ($this->careers->isEmpty()) {
            if (auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
                $this->modalMessage = 'El usuario seleccionado no tiene una carrera asignada. Asigne una carrera antes de matricularlo.';
                $this->modal = true;
            } else {
                $this->blocked = true;
            }

            return;
        }

        if (! $this->careerId) {
            $this->careerId = $this->careers->first()->id;
        }
    }

    #[Computed]
    public function targetUser()
    {
        return $this->getTargetUser($this->user_id);
    }

    #[Computed]
    public function careers()
    {
        return $this->targetUser->careers;
    }

    #[Computed]
    public function subjects()
    {
        if (! $this->careerId) {
            return collect();
        }

        return Subject::where('career_id', $this->careerId)->get();
    }

    #[Computed]
    public function enrolledSubjectIds()
    {
        if (! $this->targetUser || ! $this->careerId) {
            return [];
        }

        return \App\Models\Enrollment::where('user_id', $this->targetUser->id)
            ->whereHas('subject', function ($query) {
                $query->where('career_id', $this->careerId);
            })
            ->pluck('subject_id')
            ->toArray();
    }

    public function toggleEnrollment($subjectId)
    {
        $targetId = $this->targetUser->id;

        if (in_array($subjectId, $this->enrolledSubjectIds)) {
            \App\Models\Enrollment::where('user_id', $targetId)
                ->where('subject_id', $subjectId)
                ->delete();
            $this->success('Desmatriculado correctamente.');
        } else {
            \App\Models\Enrollment::create([
                'user_id' => $targetId,
                'subject_id' => $subjectId,
                'status' => 'active',
            ]);
            $this->success('Matriculado correctamente.');
        }

        // Clear computed property cache to reflect changes in UI
        unset($this->enrolledSubjectIds);
    }

    public function render()
    {
        return view('livewire.enrollment');
    }
}
