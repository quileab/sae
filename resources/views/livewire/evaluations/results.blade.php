<?php

use App\Models\OnlineEvaluation;
use App\Models\EvaluationAttempt;
use App\Models\Grade;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public ?OnlineEvaluation $evaluation = null;
    public $isTeacher = false;

    // Manual grading state
    public $gradingAttemptId = null;
    public $manualScores = [];
    public $feedback = '';

    public function mount($id)
    {
        $this->evaluation = OnlineEvaluation::findOrFail($id);
        $user = auth()->user();

        $this->isTeacher = $user->hasRole('admin') || $user->hasRole('teacher') || $user->hasRole('principal') || $user->hasRole('director');

        if (!$this->isTeacher) {
            $hasAttempts = EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['submitted', 'graded'])
                ->exists();
                
            if (!$hasAttempts) {
                abort(403, 'Aún no has entregado esta evaluación.');
            }
        }
    }

    public function getFinalGrade($studentAttempts)
    {
        $validAttempts = $studentAttempts->where('status', 'graded');
        if ($validAttempts->isEmpty()) return null;

        $scores = $validAttempts->pluck('score');
        switch ($this->evaluation->grading_method) {
            case 'average': return round($scores->average(), 2);
            case 'first': return $validAttempts->sortBy('attempt_number')->first()->score;
            case 'last': return $validAttempts->sortByDesc('attempt_number')->first()->score;
            case 'highest':
            default: return $scores->max();
        }
    }

    #[Computed]
    public function stats()
    {
        if (!$this->isTeacher) return null;

        $attemptsByUser = EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->whereIn('status', ['submitted', 'graded'])
            ->get()
            ->groupBy('user_id');

        if ($attemptsByUser->isEmpty()) {
            return ['total' => 0, 'average' => 0, 'approved' => 0, 'max' => 0, 'min' => 0, 'passRate' => 0];
        }

        $finalScores = collect();
        foreach ($attemptsByUser as $userId => $attempts) {
            $final = $this->getFinalGrade($attempts);
            if ($final !== null) {
                $finalScores->push($final);
            }
        }

        if ($finalScores->isEmpty()) {
            return ['total' => $attemptsByUser->count(), 'average' => 0, 'approved' => 0, 'max' => 0, 'min' => 0, 'passRate' => 0];
        }

        $passingScore = $this->evaluation->passing_score;
        $approvedCount = $finalScores->filter(fn($score) => $score >= $passingScore)->count();

        return [
            'total' => $attemptsByUser->count(), // Total students who submitted
            'average' => round($finalScores->average(), 2),
            'approved' => $approvedCount,
            'passRate' => round(($approvedCount / $finalScores->count()) * 100),
            'max' => $finalScores->max(),
            'min' => $finalScores->min()
        ];
    }

    #[Computed]
    public function attempts()
    {
        if (!$this->isTeacher) return collect();

        return EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->with('user')
            ->orderBy('user_id')
            ->orderByDesc('attempt_number')
            ->get();
    }

    #[Computed]
    public function studentAttempts()
    {
        if ($this->isTeacher) return collect();

        return EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->where('user_id', auth()->id())
            ->whereIn('status', ['submitted', 'graded'])
            ->orderByDesc('attempt_number')
            ->get();
    }

    public function openGrading($attemptId)
    {
        $attempt = EvaluationAttempt::find($attemptId);
        $this->gradingAttemptId = $attempt->id;
        $this->manualScores = $attempt->block_scores ?? [];
        $this->feedback = $attempt->feedback ?? '';
    }

    public function saveGrading()
    {
        $attempt = EvaluationAttempt::find($this->gradingAttemptId);
        if (!$attempt) return;

        // Recalcular el puntaje final basado en manualScores
        $totalEarned = array_sum($this->manualScores);
        $maxScore = \App\Services\Evaluations\EvaluationContent::getMaxScore($this->evaluation->content ?? []);
        $finalScore = $maxScore > 0 ? ($totalEarned / $maxScore) * 10 : 0;

        $attempt->update([
            'block_scores' => $this->manualScores,
            'score' => round($finalScore, 2),
            'status' => 'graded',
            'feedback' => $this->feedback
        ]);

        $this->success('Corrección guardada.');
        $this->gradingAttemptId = null;
    }

    public function syncGrades()
    {
        if (!$this->isTeacher) return;

        if (!$this->evaluation->class_session_id) {
            $this->error('Esta evaluación no está vinculada a una Clase.');
            return;
        }

        $attemptsByUser = EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->get()
            ->groupBy('user_id');

        $synced = 0;
        $passingScore = $this->evaluation->passing_score;

        foreach ($attemptsByUser as $userId => $userAttempts) {
            $finalGrade = $this->getFinalGrade($userAttempts);
            
            if ($finalGrade !== null) {
                Grade::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'class_session_id' => $this->evaluation->class_session_id,
                        'type' => Grade::TYPE_EVALUATION,
                    ],
                    [
                        'grade' => $finalGrade,
                        'approved' => $finalGrade >= $passingScore,
                        'attendance' => true,
                        'comments' => 'Evaluación Online: ' . $this->evaluation->title,
                    ]
                );
                $synced++;
            }
        }

        $this->success("Se sincronizaron $synced notas finales basadas en el método de calificación de la evaluación.");
    }
}
?>

<div class="max-w-7xl mx-auto pb-10">
    <!-- Header General -->
    <div class="flex justify-between items-start mb-8">
        <div>
            <div class="text-sm font-bold text-primary uppercase tracking-wider mb-1">{{ $evaluation->subject->name }}</div>
            <h1 class="text-3xl font-extrabold">{{ $evaluation->title }} - Resultados</h1>
            <div class="text-sm text-base-content/70 mt-1">
                Método de calificación: 
                <span class="font-bold">
                    @if($evaluation->grading_method === 'highest') Nota más alta
                    @elseif($evaluation->grading_method === 'average') Promedio
                    @elseif($evaluation->grading_method === 'first') Primer intento
                    @elseif($evaluation->grading_method === 'last') Último intento
                    @endif
                </span>
                | Nota de aprobación: <span class="font-bold">{{ $evaluation->passing_score }}</span>
            </div>
        </div>
        <div class="flex gap-2">
            @if($this->isTeacher)
                <x-button label="Volver a la Clase" icon="o-arrow-left" link="/class-sessions" class="btn-ghost" />
                @if($evaluation->class_session_id)
                    <x-button label="Sincronizar Notas" icon="o-arrows-right-left" class="btn-primary" wire:click="syncGrades" wire:confirm="Esto actualizará las calificaciones finales de los alumnos en el libro de temas. ¿Continuar?" />
                @endif
            @else
                <x-button label="Volver al Inicio" icon="o-home" link="/dashboard" class="btn-ghost" />
            @endif
        </div>
    </div>

    @if($this->isTeacher)
        @if($gradingAttemptId)
            @php
                $attemptToGrade = EvaluationAttempt::find($gradingAttemptId);
                $sections = $evaluation->content['sections'] ?? [];
            @endphp
            <x-card title="Corrigiendo: {{ $attemptToGrade->user->fullname }} (Intento {{ $attemptToGrade->attempt_number }})">
                <x-slot:menu>
                    <x-button icon="o-x-mark" class="btn-ghost btn-sm" wire:click="$set('gradingAttemptId', null)" />
                </x-slot:menu>

                <div class="space-y-8">
                    @foreach($sections as $sIndex => $section)
                        <div class="space-y-4">
                            <h3 class="font-bold text-lg border-b pb-2">{{ $section['title'] ?? 'Sección' }}</h3>
                            @foreach($section['blocks'] as $block)
                                @php 
                                    $bId = $block['id']; 
                                    $studentAnswer = $attemptToGrade->answers[$bId] ?? null;
                                @endphp
                                <div class="bg-base-200 p-4 rounded-lg">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="font-semibold">{{ $block['prompt'] }}</div>
                                        <div class="w-24">
                                            <x-input wire:model="manualScores.{{ $bId }}" type="number" step="0.1" max="{{ $block['points'] ?? 1 }}" min="0" suffix="/ {{ $block['points'] ?? 1 }}" class="input-sm" />
                                        </div>
                                    </div>
                                    <div class="bg-base-100 p-3 rounded text-sm italic border border-base-300">
                                        @if(is_array($studentAnswer))
                                            <pre class="text-xs whitespace-pre-wrap">{{ json_encode($studentAnswer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @else
                                            {{ $studentAnswer ?: 'Sin respuesta' }}
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <x-textarea wire:model="feedback" label="Feedback General" rows="3" />

                    <div class="text-right">
                        <x-button label="Guardar Corrección" icon="o-check" class="btn-primary" wire:click="saveGrading" />
                    </div>
                </div>
            </x-card>
        @else
            <!-- KPIs Docente -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <x-stat title="Estudiantes (Entregas)" value="{{ $this->stats['total'] }}" icon="o-users" class="bg-base-100 shadow-sm border border-base-200" />
                <x-stat title="Promedio General" value="{{ $this->stats['average'] }}" icon="o-chart-bar" class="bg-base-100 shadow-sm border border-base-200" />
                <x-stat title="Aprobados" value="{{ $this->stats['passRate'] ?? 0 }}%" description="{{ $this->stats['approved'] }} alumnos" icon="o-check-badge" class="bg-base-100 shadow-sm border border-base-200 text-success" />
                <x-stat title="Rango de Notas" value="{{ $this->stats['min'] }} - {{ $this->stats['max'] }}" icon="o-arrows-up-down" class="bg-base-100 shadow-sm border border-base-200" />
            </div>

            <x-card title="Detalle de Intentos por Estudiante">
                <div class="overflow-x-auto">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Intento</th>
                                <th>Estado</th>
                                <th>Entrega</th>
                                <th>Calificación</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($this->attempts as $attempt)
                                <tr>
                                    <td class="font-bold">{{ $attempt->user->fullname }}</td>
                                    <td>{{ $attempt->attempt_number }}</td>
                                    <td>
                                        <div class="badge {{ $attempt->status_badge }}">{{ $attempt->status_label }}</div>
                                    </td>
                                    <td>{{ $attempt->submitted_at ? $attempt->submitted_at->format('d/m H:i') : '-' }}</td>
                                    <td>
                                        @if($attempt->isFinished())
                                            <span class="font-bold text-lg {{ $attempt->score >= $evaluation->passing_score ? 'text-success' : 'text-error' }}">
                                                {{ $attempt->score }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($attempt->isFinished())
                                            <x-button icon="o-pencil-square" class="btn-sm btn-ghost" wire:click="openGrading({{ $attempt->id }})" tooltip="Corregir" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4">No hay intentos registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    @else
        <!-- Vista Estudiante (Historial y Nota Final) -->
        @php
            $attempts = $this->studentAttempts;
            $finalGrade = $this->getFinalGrade($attempts);
            $passingScore = $evaluation->passing_score;
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="md:col-span-1">
                <div class="bg-primary/10 border border-primary/20 rounded-xl p-6 text-center mb-8 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-primary">Calificación Final</h2>
                    @if($finalGrade !== null)
                        <div class="text-5xl font-black mt-2 {{ $finalGrade >= $passingScore ? 'text-success' : 'text-error' }}">
                            {{ $finalGrade }} <span class="text-xl text-base-content/50">/ 10</span>
                        </div>
                        @if($finalGrade >= $passingScore)
                            <div class="badge badge-success mt-4 badge-lg font-bold">¡Aprobado!</div>
                        @else
                            <div class="badge badge-error mt-4 badge-lg font-bold text-error-content">No Aprobado</div>
                        @endif
                    @else
                        <div class="text-2xl font-bold mt-4 text-base-content/70">
                            Pendiente
                        </div>
                        <p class="text-sm mt-2">Esperando corrección del docente para calcular la nota final.</p>
                    @endif
                </div>

                @if($evaluation->max_attempts > 0 && $attempts->count() < $evaluation->max_attempts)
                    <div class="mb-8">
                        <x-button label="Realizar nuevo intento" icon="o-play" link="{{ route('evaluations.take', ['id' => $evaluation->id]) }}" class="btn-primary w-full" />
                        <p class="text-xs text-center mt-2 text-base-content/70">Intentos disponibles: {{ $evaluation->max_attempts - $attempts->count() }}</p>
                    </div>
                @endif
            </div>

            <div class="md:col-span-2">
                <x-card title="Historial de Intentos">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>Intento</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th>Calificación</th>
                                    <th>Feedback</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attempts as $attempt)
                                    <tr>
                                        <td class="font-bold">{{ $attempt->attempt_number }}</td>
                                        <td>{{ $attempt->submitted_at ? $attempt->submitted_at->format('d/m/Y H:i') : '-' }}</td>
                                        <td>
                                            <div class="badge {{ $attempt->status_badge }}">{{ $attempt->status_label }}</div>
                                        </td>
                                        <td>
                                            @if($attempt->isFinished())
                                                <span class="font-bold {{ $attempt->score >= $passingScore ? 'text-success' : 'text-error' }}">
                                                    {{ $attempt->score }}
                                                </span>
                                            @else
                                                <span class="text-base-content/50">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($attempt->feedback))
                                                <button class="btn btn-xs btn-ghost" onclick="my_modal_{{ $attempt->id }}.showModal()">Ver Feedback</button>
                                                <dialog id="my_modal_{{ $attempt->id }}" class="modal">
                                                    <div class="modal-box">
                                                        <h3 class="font-bold text-lg mb-4">Feedback - Intento {{ $attempt->attempt_number }}</h3>
                                                        <p class="whitespace-pre-wrap">{{ $attempt->feedback }}</p>
                                                        <div class="modal-action">
                                                            <form method="dialog">
                                                                <button class="btn">Cerrar</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </dialog>
                                            @else
                                                <span class="text-xs text-base-content/50">Sin feedback</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        </div>
    @endif
</div>
