<?php

use App\Models\OnlineEvaluation;
use App\Models\Subject;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    #[Url(as: 'subject_id')]
    public $subject_id = null;

    public $subjects = [];

    public function mount()
    {
        $user = auth()->user();

        $this->authorize('viewAny', OnlineEvaluation::class);

        if ($user->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            $this->subjects = Subject::orderBy('name')->get();
        } else {
            $this->subjects = $user->subjects()->orderBy('name')->get();
        }

        if (!$this->subject_id && $this->subjects->count() > 0) {
            $this->subject_id = session('current_subject_id') ?? $this->subjects->first()->id;
        }

        if ($this->subject_id) {
            session()->put('current_subject_id', $this->subject_id);
        }
    }

    public function updatedSubjectId()
    {
        session()->put('current_subject_id', $this->subject_id);
    }

    #[Computed]
    public function evaluations()
    {
        if (! $this->subject_id) {
            return collect();
        }

        return OnlineEvaluation::where('subject_id', $this->subject_id)
            ->manageableBy(auth()->user())
            ->with(['teacher', 'classSession'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function deleteEvaluation($id)
    {
        $evaluation = OnlineEvaluation::findOrFail($id);

        $this->authorize('delete', $evaluation);

        $evaluation->delete();

        $this->success('Evaluación eliminada correctamente.');
    }
}
?>

<div>
    <x-header title="Evaluaciones Online" subtitle="Gestiona las evaluaciones para tus materias">
        <x-slot:middle class="!justify-end">
            <x-select 
                wire:model.live="subject_id" 
                :options="$subjects" 
                option-value="id" 
                option-label="name" 
                placeholder="Seleccionar materia..." 
                icon="o-book-open" 
                class="min-w-64 bg-base-100" 
            />
        </x-slot:middle>
        <x-slot:actions>
            @if($subject_id)
                <x-button icon="o-plus" label="Nueva Evaluación" link="{{ route('evaluations.create', ['subject_id' => $subject_id]) }}" class="btn-primary" />
            @endif
        </x-slot:actions>
    </x-header>

    @if(!$subject_id)
        <x-alert icon="o-information-circle" class="alert-info">
            Selecciona una materia para ver o crear evaluaciones.
        </x-alert>
    @else
        <x-card>
            @if($this->evaluations->isEmpty())
                <div class="text-center py-10">
                    <x-icon name="o-document-text" class="w-16 h-16 mx-auto text-base-300 mb-4" />
                    <h3 class="text-lg font-bold text-base-content">No hay evaluaciones</h3>
                    <p class="text-base-content/70 mt-2">Aún no se han creado evaluaciones para esta materia.</p>
                    <x-button icon="o-plus" label="Crear la primera evaluación" link="{{ route('evaluations.create', ['subject_id' => $subject_id]) }}" class="btn-primary mt-4" />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Estado</th>
                                <th>Fecha Límite</th>
                                <th>Clase (Libro)</th>
                                <th>Duración</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->evaluations as $eval)
                                <tr>
                                    <td>
                                        <div class="font-bold">{{ $eval->title }}</div>
                                        <div class="text-xs opacity-70">Creada por {{ $eval->teacher->fullname }}</div>
                                    </td>
                                    <td>
                                        <div class="badge {{ $eval->status_badge }}">{{ $eval->status_label }}</div>
                                    </td>
                                    <td>
                                        @if($eval->ends_at)
                                            {{ $eval->ends_at->format('d/m/Y H:i') }}
                                            @if($eval->ends_at->isPast())
                                                <div class="text-xs opacity-60">vencida</div>
                                            @endif
                                        @else
                                            <span class="text-base-content/50">Sin límite</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($eval->classSession)
                                            <span class="text-sm">Clase #{{ $eval->classSession->class_number }} ({{ $eval->classSession->date->format('d/m/Y') }})</span>
                                        @else
                                            <span class="text-base-content/50 italic">No vinculada</span>
                                        @endif
                                    </td>
                                    <td>{{ $eval->duration_minutes }} min</td>
                                    <td class="text-right">
                                        <x-button icon="o-eye" link="{{ route('evaluations.take', $eval->id) }}" class="btn-sm btn-ghost text-info" tooltip="Vista Previa" />
                                        <x-button icon="o-chart-bar" link="{{ route('evaluations.results', $eval->id) }}" class="btn-sm btn-ghost text-success" tooltip="Ver Resultados" />
                                        <x-button icon="o-pencil" link="{{ route('evaluations.edit', $eval->id) }}" class="btn-sm btn-ghost" tooltip="Editar" />
                                        <x-button icon="o-trash" wire:click="deleteEvaluation({{ $eval->id }})" wire:confirm="¿Estás seguro de que deseas eliminar esta evaluación y todos los intentos asociados?" class="btn-sm btn-ghost text-error" tooltip="Eliminar" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    @endif
</div>
