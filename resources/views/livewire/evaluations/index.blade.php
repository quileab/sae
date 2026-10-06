<?php

use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Services\Evaluations\StudentEvaluationState;
use Illuminate\Support\Collection;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination;

    #[Url(as: 'subject_id')]
    public $subject_id = null;

    public $subjects = [];

    /**
     * Students reach this same screen and get their own list.
     */
    public $isStudent = false;

    public $showFilters = false;

    public function mount()
    {
        $user = auth()->user();

        $this->isStudent = $user->hasRole('student') && ! $user->hasAnyRole(
            ['admin', 'teacher', 'principal', 'director', 'administrative']
        );

        if ($this->isStudent) {
            $this->authorize('takeAny', OnlineEvaluation::class);

            return;
        }

        $this->authorize('viewAny', OnlineEvaluation::class);

        if ($user->hasAnyRole(['admin', 'principal', 'director', 'administrative'])) {
            $this->subjects = Subject::orderBy('name')->get();
        } else {
            $this->subjects = $user->subjects()->orderBy('name')->get();
        }

        if (! $this->subject_id && $this->subjects->count() > 0) {
            $this->subject_id = session('current_subject_id') ?? $this->subjects->first()->id;
        }

        if ($this->subject_id) {
            session()->put('current_subject_id', $this->subject_id);
        }
    }

    public function updatedSubjectId()
    {
        session()->put('current_subject_id', $this->subject_id);

        $this->resetPage();
    }

    #[Computed]
    public function evaluations()
    {
        if ($this->isStudent) {
            return collect();
        }

        if (! $this->subject_id) {
            return collect();
        }

        return OnlineEvaluation::where('subject_id', $this->subject_id)
            ->manageableBy(auth()->user())
            ->with(['teacher', 'classSession'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * The student's own evaluations, each described by StudentEvaluationState.
     *
     * @return Collection<int, array{evaluation: OnlineEvaluation, state: array<string, mixed>}>
     */
    #[Computed]
    public function myEvaluations()
    {
        if (! $this->isStudent) {
            return collect();
        }

        $resolver = app(StudentEvaluationState::class);

        return $resolver->forStudent(auth()->user())
            ->map(fn (OnlineEvaluation $evaluation) => [
                'evaluation' => $evaluation,
                'state' => $resolver->describe(
                    $evaluation,
                    auth()->user(),
                    $evaluation->attempts->first()
                ),
            ]);
    }

    /**
     * Counts per state, used for the summary chips.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function myEvaluationsSummary()
    {
        return $this->myEvaluations
            ->groupBy(fn (array $row) => $row['state']['key'])
            ->map->count()
            ->all();
    }

    public function deleteEvaluation($id)
    {
        $evaluation = OnlineEvaluation::findOrFail($id);

        $this->authorize('delete', $evaluation);

        if ($evaluation->attempts()->exists()) {
            $this->error('No se puede eliminar: la evaluación ya tiene intentos registrados.');

            return;
        }

        $evaluation->delete();

        $this->success('Evaluación eliminada correctamente.');
    }
} ?>

<div>
    @if($isStudent)
        {{-- STUDENT VIEW --}}
        <x-header title="Mis Evaluaciones" subtitle="Evaluaciones disponibles para tus materias" separator />

        @php $summary = $this->myEvaluationsSummary; @endphp

        @if($this->myEvaluations->isEmpty())
            <x-card>
                <div class="text-center py-12">
                    <x-icon name="o-clipboard-document-check" class="w-16 h-16 mx-auto text-base-300 mb-4" />
                    <h3 class="text-lg font-bold">Todavía no tenés evaluaciones</h3>
                    <p class="text-base-content/70 mt-2">
                        Cuando un docente publique una evaluación para una materia en la que estás
                        inscrito, la vas a ver acá.
                    </p>
                </div>
            </x-card>
        @else
            <div class="flex flex-wrap gap-2 mb-4">
                <x-badge value="Total: {{ $this->myEvaluations->count() }}" class="badge-neutral" />
                @if(($summary['available'] ?? 0) > 0)
                    <x-badge value="Disponibles: {{ $summary['available'] }}" class="badge-primary" />
                @endif
                @if(($summary['in_progress'] ?? 0) > 0)
                    <x-badge value="En curso: {{ $summary['in_progress'] }}" class="badge-info" />
                @endif
                @if(($summary['pending_grading'] ?? 0) > 0)
                    <x-badge value="Esperando corrección: {{ $summary['pending_grading'] }}" class="badge-warning" />
                @endif
                @if(($summary['graded'] ?? 0) > 0)
                    <x-badge value="Corregidas: {{ $summary['graded'] }}" class="badge-success" />
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 pb-10">
                @foreach($this->myEvaluations as $row)
                    @php
                        $evaluation = $row['evaluation'];
                        $state = $row['state'];
                        $progress = $state['total'] > 0
                            ? (int) round($state['answered'] / $state['total'] * 100)
                            : 0;
                    @endphp
                    <div wire:key="eval-card-{{ $evaluation->id }}" class="card bg-base-100 border border-base-200 shadow-sm flex flex-col">
                        <div class="p-4 flex-1">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="badge {{ $state['badge'] }} badge-sm">{{ $state['label'] }}</span>
                                <span class="text-xs text-base-content/50">{{ $evaluation->duration_minutes }} min</span>
                            </div>

                            <h3 class="font-bold leading-snug">{{ $evaluation->title }}</h3>

                            <div class="text-xs text-base-content/60 mt-1">
                                {{ $evaluation->subject->name }}
                            </div>

                            @if($state['key'] === App\Services\Evaluations\StudentEvaluationState::UPCOMING && $evaluation->starts_at)
                                <div class="text-xs mt-2 flex items-center gap-1">
                                    <x-icon name="o-calendar" class="w-3.5 h-3.5" />
                                    Abre el {{ $evaluation->starts_at->format('d/m/Y H:i') }}
                                </div>
                            @endif

                            @if($evaluation->ends_at)
                                <div class="text-xs mt-1 flex items-center gap-1 {{ $evaluation->ends_at->isPast() ? 'text-error' : '' }}">
                                    <x-icon name="o-clock" class="w-3.5 h-3.5" />
                                    @if($evaluation->ends_at->isFuture())
                                        Vence en {{ $evaluation->ends_at->diffForHumans() }}
                                    @else
                                        Venció el {{ $evaluation->ends_at->format('d/m/Y H:i') }}
                                    @endif
                                </div>
                            @endif

                            @if($state['key'] === App\Services\Evaluations\StudentEvaluationState::IN_PROGRESS && $state['total'] > 0)
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span>Respondidas</span>
                                        <span class="tabular-nums">{{ $state['answered'] }}/{{ $state['total'] }}</span>
                                    </div>
                                    <progress class="progress progress-primary w-full" value="{{ $progress }}" max="100"></progress>
                                </div>
                            @endif

                            @if($state['attempts_allowed'] > 0 && $state['attempts_used'] > 0)
                                <div class="text-xs mt-3 text-base-content/60">
                                    Intento {{ $state['attempts_used'] }} de {{ $state['attempts_allowed'] }}
                                </div>
                            @endif

                            @if($state['key'] === App\Services\Evaluations\StudentEvaluationState::GRADED && $evaluation->attempts->first()?->score !== null)
                                @php
                                    $latest = $evaluation->attempts->first();
                                    $passes = (float) $latest->score >= $evaluation->passing_score;
                                @endphp
                                <div class="mt-3 flex items-baseline gap-1">
                                    <span class="text-3xl font-black {{ $passes ? 'text-success' : 'text-error' }}">
                                        {{ number_format((float) $latest->score, 2) }}
                                    </span>
                                    <span class="text-sm text-base-content/50">/ 10</span>
                                </div>
                            @endif
                        </div>

                        <div class="p-4 pt-0 flex gap-2">
                            @if($state['actionable'])
                                <x-button
                                    label="{{ $state['key'] === App\Services\Evaluations\StudentEvaluationState::IN_PROGRESS ? 'Continuar' : ($state['key'] === App\Services\Evaluations\StudentEvaluationState::GRADED ? 'Nuevo intento' : 'Comenzar') }}"
                                    icon="{{ $state['key'] === App\Services\Evaluations\StudentEvaluationState::IN_PROGRESS ? 'o-play' : 'o-pencil-square' }}"
                                    class="btn-primary btn-sm flex-1"
                                    link="{{ route('evaluations.take', $evaluation->id) }}"
                                />
                            @endif

                            @if($evaluation->attempts->isNotEmpty())
                                <x-button
                                    label="Resultados"
                                    icon="o-chart-bar"
                                    class="btn-ghost btn-sm"
                                    link="{{ route('evaluations.results', $evaluation->id) }}"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        {{-- STAFF VIEW --}}
        <x-header title="Evaluaciones Online" subtitle="Gestiona las evaluaciones de tus materias">
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
                                    <tr wire:key="eval-row-{{ $eval->id }}">
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
                                            <x-button icon="o-trash" wire:click="deleteEvaluation({{ $eval->id }})" wire:confirm="¿Eliminar esta evaluación?" class="btn-sm btn-ghost text-error" tooltip="Eliminar" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endif
    @endif
</div>