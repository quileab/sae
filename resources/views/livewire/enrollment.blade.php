<?php
use Livewire\Volt\Component;
use App\Models\Enrollment;
use App\Models\Subject;
use Mary\Traits\Toast;

new class extends Component {
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
        if (!$user) {
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

    public function getUser(): object
    {
        if (session()->has('user_id')) {
            $user = \App\Models\User::find(session('user_id'));
        } else {
            $user = auth()->user();
            //save user to session
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
        $this->enrolledSubjects = Enrollment::where('user_id', $user_id)
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

    public function updated()
    {
        $this->loadSubjects();
        $this->loadEnrollments();
        $this->skipMount();
    }

}; ?>

<div>
    @if($blocked)
        <x-alert icon="o-exclamation-triangle" class="alert-error" title="Acceso Denegado">
            No tienes una carrera asignada. Por favor, contacta con administración para regularizar tu situación antes de matricularte.
        </x-alert>
    @else
        <h1 class="text-2xl font-bold">Materias »
            {{ session()->get('user_id_name') ? session()->get('user_id_name') : auth()->user()->fullname }}
        </h1>
        <x-select icon="o-academic-cap" :options="$careers" wire:model.lazy="careerId" />

        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">

            @foreach($subjects as $subject)
                <div class="border border-white/10 rounded-lg overflow-hidden text-black dark:text-white">

                    <p class="p-2 border-b border-white/50 bg-blue-500/50 h-16 overflow-hidden">
                        <small>{{ $subject->id }}</small> <strong>{{ $subject->name }}</strong>
                    </p>

                    <div class="justify-end flex p-2 bg-gray-500/40">
                        <x-button label="{{ in_array($subject->id, $enrolledSubjects) ? 'Desmatricularse' : 'Matricularse' }}"
                            wire:click="save" wire:click="toggleEnrollment({{ $subject->id }})"
                            class="btn-sm {{ in_array($subject->id, $enrolledSubjects) ? 'bg-red-500/50 text-white' : 'bg-lime-500/50 text-white' }}" />
                    </div>

                </div>
            @endforeach

        </div>
    @endif
    <x-modal wire:model="modal" class="backdrop-blur" persistent>
        <div class="mb-5">Haga click para ser redirigido</div>
        <x-button label="CONTINUAR" link="/users" class="btn-primary" />
    </x-modal>
</div>