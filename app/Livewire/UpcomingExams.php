<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Carbon\Carbon;

class UpcomingExams extends Component
{
    public $selectedProfessorId = null;
    public $professors;

    public function mount()
    {
        $this->professors = User::where('role', 'teacher')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();
    }

    public function render()
    {
        $user = Auth::user();
        $query = Event::where('start', '>=', Carbon::now())
            ->whereNotNull('presidente_id') // Consider only events with a president as exams
            ->with(['subject.career', 'presidente', 'vocal1', 'vocal2'])
            ->orderBy('start', 'asc');

        if ($this->selectedProfessorId) {
            $query->where(function ($q) {
                $q->where('presidente_id', $this->selectedProfessorId)
                    ->orWhere('vocal1_id', $this->selectedProfessorId)
                    ->orWhere('vocal2_id', $this->selectedProfessorId);
            });
        }

        if ($user->hasRole('teacher')) {
            $query->where(function ($q) use ($user) {
                $q->where('presidente_id', $user->id)
                    ->orWhere('vocal1_id', $user->id)
                    ->orWhere('vocal2_id', $user->id);
            });
        } elseif ($user->hasRole('student')) {
            $careerIds = $user->careers->pluck('id');
            $query->whereHas('subject', function ($q) use ($careerIds) {
                $q->whereIn('career_id', $careerIds);
            });
        }
        // For admin and other roles, no additional filtering is applied, so they see all.

        $exams = $query->get();

        if ($user->hasRole('teacher')) {
            // Add the role for the teacher
            $exams->each(function ($exam) use ($user) {
                if ($exam->presidente_id == $user->id) {
                    $exam->teacher_role = 'Presidente';
                } elseif ($exam->vocal1_id == $user->id) {
                    $exam->teacher_role = 'Vocal 1';
                } elseif ($exam->vocal2_id == $user->id) {
                    $exam->teacher_role = 'Vocal 2';
                }
            });
        }

        return view('livewire.upcoming-exams', [
            'exams' => $exams,
        ]);
    }
}