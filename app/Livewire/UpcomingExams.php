<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class UpcomingExams extends Component
{
    public $selectedProfessorId = null;

    #[Computed]
    public function professors()
    {
        return Cache::remember('professors_list', 3600, function () {
            return User::where('role', 'teacher')
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->get();
        });
    }

    #[Computed]
    public function exams()
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

        $exams = $query->get();

        if ($user->hasRole('teacher')) {
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

        return $exams;
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-card title="Próximos Exámenes" shadow-md class="bg-base-200">
            <div class="flex justify-center items-center h-32">
                <x-loading class="loading-md text-primary" />
            </div>
        </x-card>
        HTML;
    }

    public function render()
    {
        return view('livewire.upcoming-exams');
    }
}
