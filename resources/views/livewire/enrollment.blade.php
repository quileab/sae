<?php

use Livewire\Volt\Component;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\Career;

new class extends Component {
    public $careerId; // Carrera seleccionada
    public $userType; // Tipo de usuario (student o teacher)
    public $enrolledSubjects = []; // Materias en las que está matriculado
    public $subjects = []; // Todas las materias de la carrera seleccionada

    public function mount()
    {
        if(session('career_id')) {
            $careerId = session('career_id');
        } else {
            $careerId = Career::first()->id;
        }
        if(session()->has('user_id')) {
            $user=\App\Models\User::find(session('user_id'));
            $userType = $user->hasRole('teacher') ? 'teacher' : 'student';
        } else {
            $userType = 'student';
        }

        $this->careerId = $careerId;
        $this->userType = $userType;

        // Cargar las materias de la carrera
        $this->loadSubjects();
        $this->loadEnrollments();
    }

    public function loadSubjects()
    {
        $this->subjects = Subject::where('career_id', $this->careerId)->get();
    }

    public function loadEnrollments()
    {
        $this->enrolledSubjects = Enrollment::where('user_id', session()->get('user_id'))
            ->whereHas('subject', function ($query) {
                $query->where('career_id', $this->careerId);
            })
            ->pluck('subject_id') // Solo obtiene los IDs de las materias matriculadas
            ->toArray();
    }

    public function toggleEnrollment($subjectId)
    {
        $user_id=session()->get('user_id');
        if (in_array($subjectId, $this->enrolledSubjects)) {
            // Si ya está matriculado, eliminar la inscripción
            Enrollment::where('user_id', $user_id)
                ->where('subject_id', $subjectId)
                ->delete();
            $this->enrolledSubjects = array_diff($this->enrolledSubjects, [$subjectId]);
        } else {
            // Si no está matriculado, agregar la inscripción
            Enrollment::create([
                'user_id' => $user_id,
                'subject_id' => $subjectId,
                'status' => 'active', // Por defecto
            ]);
            $this->enrolledSubjects[] = $subjectId;
        }
    }

}; ?>

<div>
    <h3>Materias de la Carrera Seleccionada</h3>

    <div class="grid grid-cols-1 gap-2 md:grid-cols-3">

        @foreach($subjects as $subject)


        <x-card subtitle="{{ $subject->name }}" separator progress-indicator
            class="{{ in_array($subject->id, $enrolledSubjects) ? 'bg-lime-500/20 text-white' : '' }}">
            <x-slot:menu>
                <x-button icon="o-share" class="btn-circle btn-sm" />
                <x-icon name="o-heart" class="cursor-pointer" />
            </x-slot:menu>
            <x-button label="{{ in_array($subject->id, $enrolledSubjects) ? 'Desmatricularse' : 'Matricularse' }}" wire:click="save" 
                wire:click="toggleEnrollment({{ $subject->id }})"
                />
        </x-card>
        @endforeach

    </div>

</div>
