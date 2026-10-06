<?php

use App\Models\OnlineEvaluation;
use App\Models\EvaluationAttempt;
use App\Models\Grade;
use App\Services\Evaluations\AttemptGrader;
use App\Services\Evaluations\FinalGradeResolver;
use App\Services\Evaluations\GradeSyncPreview;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public ?OnlineEvaluation $evaluation = null;
    public $isTeacher = false;

    // Manual grading state
    public $gradingAttemptId = null;
    public ?EvaluationAttempt $gradingAttempt = null;
    public $manualScores = [];
    public $feedback = '';

    // Grade sync state
    public $showSyncModal = false;
    public $syncPreview = [];
    public $syncUserIds = [];

public function mount($id)
    {
        $this->evaluation = OnlineEvaluation::findOrFail($id);
        $user = auth()->user();

        $this->authorize('results', $this->evaluation);

        $this->isTeacher = $user->hasAnyRole(['admin', 'teacher', 'principal', 'director', 'administrative']);

        if (! $this->isTeacher) {
            $hasAttempts = EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
                ->where('user_id', $user->id)
                ->finished()
                ->exists();

            if (! $hasAttempts) {
                abort(403, 'Aún no has entregado esta evaluación.');
            }
        }
    }

    public function getFinalGrade($studentAttempts)
    {
        return FinalGradeResolver::resolve($this->evaluation, $studentAttempts);
    }

    #[Computed]
    public function stats()
    {
        if (! $this->isTeacher) return null;

        $attemptsByUser = EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->finished()
            ->with('user')
            ->get()
            ->groupBy('user_id');

        if ($attemptsByUser->isEmpty()) {
            return ['total' => 0, 'average' => 0, 'approved' => 0, 'max' => 0, 'min' => 0, 'passRate' => 0];
        }

        $finalScores = collect();
        foreach ($attemptsByUser as $userAttempts) {
            $final = FinalGradeResolver::resolve($this->evaluation, $userAttempts);
            if ($final !== null) {
                $finalScores->push($final);
            }
        }

        if ($finalScores->isEmpty()) {
            return ['total' => $attemptsByUser->count(), 'average' => 0, 'approved' => 0, 'max' => 0, 'min' => 0, 'passRate' => 0, 'pending' => $attemptsByUser->count()];
        }

        $passingScore = $this->evaluation->passing_score;
        $approvedCount = $finalScores->filter(fn ($score) => $score >= $passingScore)->count();

        return [
            'total' => $finalScores->count(),
            'pending' => $attemptsByUser->count() - $finalScores->count(),
            'average' => round($finalScores->average(), 2),
            'approved' => $approvedCount,
            'passRate' => round(($approvedCount / $finalScores->count()) * 100),
            'max' => $finalScores->max(),
            'min' => $finalScores->min(),
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
        $attempt = EvaluationAttempt::findOrFail($attemptId);

        $this->authorize('grade', $this->evaluation);
        abort_unless($attempt->online_evaluation_id === $this->evaluation->id, 404);

        $this->gradingAttemptId = $attempt->id;
        $this->gradingAttempt = $attempt->load('user');
        $this->manualScores = $attempt->block_scores ?? [];
        $this->feedback = $attempt->feedback ?? '';
    }

    public function closeGrading()
    {
        $this->reset(['gradingAttemptId', 'gradingAttempt', 'manualScores', 'feedback']);
    }

    public function saveGrading()
    {
        $this->authorize('grade', $this->evaluation);

        $attempt = EvaluationAttempt::whereKey($this->gradingAttemptId)->firstOrFail();

        abort_unless($attempt->online_evaluation_id === $this->evaluation->id, 404);

        $this->validate([
            'manualScores' => 'required|array',
            'manualScores.*' => 'nullable|numeric|min:0',
            'feedback' => 'nullable|string|max:5000',
        ]);

        $grader = app(AttemptGrader::class);
        $content = $grader->contentFor($attempt);

        foreach ($grader->blocks($content) as $block) {
            // Re-check the per-block maximum server-side; the input's `max`
            // attribute is advisory only.
            $this->validate([
                'manualScores.'.$block['id'] => 'nullable|numeric|min:0|max:'.(float) ($block['points'] ?? 1),
            ]);
        }

        $grader->applyManualMarks($attempt, $this->manualScores, $this->feedback);

        unset($this->attempts, $this->stats);

        $this->success('Corrección guardada.');
        $this->closeGrading();
    }

    /**
     * Build the diff of what syncing would change. Writes nothing.
     */
    public function previewSyncGrades()
    {
        $this->authorize('syncGrades', $this->evaluation);

        if (! $this->evaluation->class_session_id) {
            $this->error('Esta evaluación no está vinculada a una Clase.');

            return;
        }

        $rows = app(GradeSyncPreview::class)->build($this->evaluation);

        if ($rows === []) {
            $this->warning('No hay intentos corregidos para sincronizar todavía.');

            return;
        }

        $this->syncPreview = $rows;
        $this->syncUserIds = collect($rows)
            ->whereIn('action', [
                GradeSyncPreview::ACTION_CREATE,
                GradeSyncPreview::ACTION_UPDATE,
                GradeSyncPreview::ACTION_CONFLICT,
            ])
            ->pluck('user_id')
            ->values()
            ->all();

        $this->showSyncModal = true;
    }

    public function closeSyncPreview()
    {
        $this->reset(['syncPreview', 'syncUserIds', 'showSyncModal']);
    }

    /**
     * Apply the previewed sync, but only for the students the teacher confirmed.
     */
    public function confirmSyncGrades()
    {
        $this->authorize('syncGrades', $this->evaluation);

        if (! $this->evaluation->class_session_id) {
            $this->error('Esta evaluación no está vinculada a una Clase.');
            $this->closeSyncPreview();

            return;
        }

        // Recompute server-side; never trust the preview payload from the client.
        $rows = app(GradeSyncPreview::class)->build($this->evaluation);
        $confirmed = array_map('intval', $this->syncUserIds ?? []);

        $synced = 0;
        $skippedConflicts = 0;

        foreach ($rows as $row) {
            if (! in_array((int) $row['user_id'], $confirmed, true)) {
                continue;
            }

            if ($row['action'] === GradeSyncPreview::ACTION_SKIPPED || $row['proposed_grade'] === null) {
                continue;
            }

            $finalGrade = (float) $row['proposed_grade'];

            Grade::updateOrCreate(
                [
                    'user_id' => $row['user_id'],
                    'class_session_id' => $this->evaluation->class_session_id,
                    'type' => Grade::TYPE_EVALUATION,
                ],
                [
                    'grade' => $finalGrade,
                    'approved' => $finalGrade >= $this->evaluation->passing_score,
                    'attendance' => true,
                    'comments' => GradeSyncPreview::PROVENANCE_PREFIX.' '.$this->evaluation->title,
                ]
            );

            $synced++;
        }

        $skippedConflicts = collect($rows)
            ->where('action', GradeSyncPreview::ACTION_CONFLICT)
            ->reject(fn ($row) => in_array((int) $row['user_id'], $confirmed, true))
            ->count();

        $this->closeSyncPreview();

        $message = "Se sincronizaron $synced notas finales al libro de temas.";
        if ($skippedConflicts > 0) {
            $message .= " $skippedConflicts notas manuales quedaron sin tocar.";
        }

        $this->success($message);
    }
}
?>

<div class="max-w-7xl mx-auto pb-10">
    <!-- Header General -->
    <div class="flex flex-col lg:flex-row justify-between items-start gap-4 mb-8">
        <div>
            <div class="text-sm font-bold text-primary uppercase tracking-wider mb-1">{{ $evaluation->subject->name }}</div>
            <h1 class="text-2xl md:text-3xl font-extrabold">{{ $evaluation->title }} <span class="text-base-content/40 font-bold">· Resultados</span></h1>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-content/70 mt-2">
                <span>Método de calificación: <span class="font-bold">{{ $evaluation->grading_method_short_label }}</span></span>
                <span>Nota de aprobación: <span class="font-bold">{{ $evaluation->passing_score }}</span></span>
                @if($evaluation->class_session_id)
                    <span>Clase: <span class="font-bold">#{{ $evaluation->classSession->class_number }}</span></span>
                @else
                    <span class="text-warning">Sin clase vinculada</span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($this->isTeacher)
                <x-button label="Volver a la Clase" icon="o-arrow-left" link="/class-sessions" class="btn-ghost" />
                @if($evaluation->class_session_id)
                    <x-button
                        label="Sincronizar Notas"
                        icon="o-arrows-right-left"
                        class="btn-primary"
                        wire:click="previewSyncGrades"
                        spinner="previewSyncGrades"
                    />
                @endif
            @else
                <x-button label="Volver al Inicio" icon="o-home" link="/dashboard" class="btn-ghost" />
            @endif
        </div>
    </div>

    @if($showSyncModal)
        @php
            $actionLabels = [
                App\Services\Evaluations\GradeSyncPreview::ACTION_CREATE => ['Nuevo', 'badge-info'],
                App\Services\Evaluations\GradeSyncPreview::ACTION_UPDATE => ['Actualizar', 'badge-warning'],
                App\Services\Evaluations\GradeSyncPreview::ACTION_CONFLICT => ['Nota manual', 'badge-error'],
                App\Services\Evaluations\GradeSyncPreview::ACTION_UNCHANGED => ['Sin cambios', 'badge-ghost'],
                App\Services\Evaluations\GradeSyncPreview::ACTION_SKIPPED => ['Sin corregir', 'badge-ghost'],
            ];
        @endphp
        <x-card title="Confirmar sincronización con el libro de temas" class="mb-8 border-warning">
            <x-slot:menu>
                <x-button icon="o-x-mark" class="btn-ghost btn-sm" wire:click="closeSyncPreview" />
            </x-slot:menu>

            <x-alert icon="o-exclamation-triangle" class="alert-warning mb-4">
                Revisá qué notas se van a escribir. Las que ya cargaste a mano en el libro
                quedan marcadas y <strong>no se tocan</strong> salvo que las marques explícitamente.
            </x-alert>

            <div class="overflow-x-auto">
                <table class="table table-sm table-zebra">
                    <thead>
                        <tr>
                            <th class="w-10"></th>
                            <th>Alumno</th>
                            <th>Acción</th>
                            <th class="text-right">Nota actual</th>
                            <th class="text-right">Nota propuesta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($syncPreview as $row)
                            @php
                                $label = $actionLabels[$row['action']] ?? ['—', 'badge-ghost'];
                                $selectable = in_array($row['action'], [
                                    App\Services\Evaluations\GradeSyncPreview::ACTION_CREATE,
                                    App\Services\Evaluations\GradeSyncPreview::ACTION_UPDATE,
                                    App\Services\Evaluations\GradeSyncPreview::ACTION_CONFLICT,
                                ], true);
                            @endphp
                            <tr class="{{ $selectable ? '' : 'opacity-50' }}">
                                <td>
                                    @if($selectable)
                                        <input
                                            type="checkbox"
                                            wire:model="syncUserIds"
                                            value="{{ $row['user_id'] }}"
                                            class="checkbox checkbox-sm checkbox-primary"
                                        />
                                    @endif
                                </td>
                                <td class="font-semibold">{{ $row['user_name'] }}</td>
                                <td>
                                    <span class="badge {{ $label[1] }}">{{ $label[0] }}</span>
                                    @if($row['action'] === App\Services\Evaluations\GradeSyncPreview::ACTION_CONFLICT)
                                        <div class="text-xs text-base-content/60 mt-1">{{ $row['current_comments'] ?: 'sin comentario' }}</div>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $row['current_grade'] ?? '—' }}</td>
                                <td class="text-right font-bold tabular-nums">{{ $row['proposed_grade'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <x-button label="Cancelar" wire:click="closeSyncPreview" />
                <x-button
                    label="Aplicar ({{ count($syncUserIds) }})"
                    icon="o-check"
                    class="btn-primary"
                    wire:click="confirmSyncGrades"
                    wire:loading.attr="disabled"
                    :disabled="empty($syncUserIds)"
                />
            </div>
        </x-card>
    @endif

    @if($this->isTeacher)
        @if($gradingAttemptId && $gradingAttempt)
            @php
                $attemptToGrade = $gradingAttempt;
                $sections = ($attemptToGrade->content_snapshot ?? $evaluation->content)['sections'] ?? [];
            @endphp
            <x-card title="Corrigiendo: {{ $attemptToGrade->user->fullname }} (Intento {{ $attemptToGrade->attempt_number }})">
                <x-slot:menu>
                    <x-button icon="o-x-mark" class="btn-ghost btn-sm" wire:click="closeGrading" />
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
                                    <div class="flex justify-between items-start gap-4 mb-2">
                                        <div class="font-semibold">
                                            @if(! empty($block['prompt']))
                                                {{ $block['prompt'] }}
                                            @else
                                                <span class="italic text-base-content/60">Sin consigna</span>
                                            @endif
                                            @php $blockType = App\Enums\EvaluationBlockType::tryFrom($block['type'] ?? ''); @endphp
                                            <span class="badge badge-ghost badge-xs ml-1">{{ $blockType?->shortLabel() ?? '?' }}</span>
                                        </div>
                                        <div class="w-28 shrink-0">
                                            <x-input
                                                wire:model="manualScores.{{ $bId }}"
                                                type="number"
                                                step="0.1"
                                                max="{{ $block['points'] ?? 1 }}"
                                                min="0"
                                                suffix="/ {{ $block['points'] ?? 1 }}"
                                                class="input-sm"
                                            />
                                        </div>
                                    </div>
                                    <div class="bg-base-100 p-3 rounded text-sm border border-base-300">
                                        <x-evaluation-answer :block="$block" :answer="$studentAnswer" />
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
                <x-stat
                    title="Alumnos corrijidos"
                    value="{{ $this->stats['total'] }}"
                    description="{{ ($this->stats['pending'] ?? 0) > 0 ? ($this->stats['pending'].' pendientes') : 'todos corregidos' }}"
                    icon="o-users"
                    class="bg-base-100 shadow-sm border border-base-200"
                />
                <x-stat
                    title="Promedio General"
                    value="{{ $this->stats['average'] }}"
                    icon="o-chart-bar"
                    class="bg-base-100 shadow-sm border border-base-200"
                />
                <x-stat
                    title="Tasa de Aprobación"
                    value="{{ $this->stats['passRate'] ?? 0 }}%"
                    description="{{ $this->stats['approved'] }} de {{ $this->stats['total'] }} aprobados"
                    icon="o-check-badge"
                    class="bg-base-100 shadow-sm border border-base-200 text-success"
                />
                <x-stat
                    title="Rango de Notas"
                    value="{{ $this->stats['min'] }} – {{ $this->stats['max'] }}"
                    icon="o-arrows-up-down"
                    class="bg-base-100 shadow-sm border border-base-200"
                />
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
