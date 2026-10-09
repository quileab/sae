<?php

use App\Models\OnlineEvaluation;
use App\Models\EvaluationAttempt;
use App\Models\Grade;
use App\Services\Evaluations\AttemptGrader;
use App\Services\Evaluations\FinalGradeResolver;
use App\Services\Evaluations\GradeSyncPreview;
use App\Services\Evaluations\GradingFileBuilder;
use App\Enums\GradingSuggestionState;
use App\Services\Evaluations\GradingFileParser;
use App\Services\Evaluations\GradingSuggestionImporter;
use App\Services\Evaluations\ParsedGradingFile;
use App\Services\Evaluations\Rubric;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component {
    use Toast, WithPagination;

    public ?OnlineEvaluation $evaluation = null;
    public $isTeacher = false;

    // Manual grading state
    public $gradingAttemptId = null;
    public ?EvaluationAttempt $gradingAttempt = null;
    public $manualScores = [];
    public $feedback = '';
    public $suggestionRejectionReason = null;
    // Position of the graded attempt within the finished attempts of the
    // evaluation, in table order. Drives the prev/next navigation.
    public $gradingPrevId = null;
    public $gradingNextId = null;
    public $gradingPosition = null;
    public $gradingTotal = null;

    // Grade sync state
    public $showSyncModal = false;
    public $syncPreview = [];
    public $syncUserIds = [];

    // Assisted grading state. The markdown is never bound with wire:model: a
    // full cohort runs to hundreds of kilobytes.
    public $showAssistModal = false;
    public $assistMode = 'grading';
    public $assistAnonymize = false;
    public $assistBlockId = null;
    public $assistMarkdown = null;
    public $assistSummary = [];
    public $assistPasted = null;
    public $assistPreview = [];
    public $assistRubricRows = 0;
    public $assistMarkRows = 0;
    public $assistErrors = [];
    public $assistWarnings = [];
    public $assistApplied = [];
    public $assistRubricApplied = [];


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

public function attemptHeaders(): array
    {
        // Compact cell padding (header['class'] reaches th and td): the six
        // default-padded columns overflow the card by a few pixels at md widths.
        return [
            ['key' => 'user_id', 'label' => 'Estudiante', 'class' => 'px-3'],
            ['key' => 'attempt_number', 'label' => 'Intento', 'class' => 'px-3'],
            ['key' => 'status', 'label' => 'Estado', 'class' => 'px-3'],
            ['key' => 'submitted_at', 'label' => 'Entrega', 'class' => 'px-3'],
            ['key' => 'score', 'label' => 'Calificación', 'class' => 'px-3'],
        ];
    }

    #[Computed]
    public function attempts()
    {
        if (! $this->isTeacher) return collect();

        return EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->with('user')
            // One grouped count for the whole page instead of a query per row.
            ->withCount([
                'gradingSuggestions as pending_suggestions_count' => fn ($query) => $query->pending(),
            ])
            ->orderBy('user_id')
            ->orderByDesc('attempt_number')
            ->paginate(20);
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
        if (blank($attemptId)) {
            return;
        }

        $attempt = EvaluationAttempt::findOrFail($attemptId);

        $this->authorize('grade', $this->evaluation);
        abort_unless($attempt->online_evaluation_id === $this->evaluation->id, 404);

        $this->gradingAttemptId = $attempt->id;
        $this->gradingAttempt = $attempt->load(['user', 'gradingSuggestions']);
        $this->manualScores = $attempt->block_scores ?? [];
        $this->feedback = $attempt->feedback ?? '';

        // Neighbors in table order, so prev/next never loses the teacher's place.
        $orderedIds = EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->finished()
            ->orderBy('user_id')
            ->orderByDesc('attempt_number')
            ->pluck('id');
        $this->gradingTotal = $orderedIds->count();
        $index = $orderedIds->search($attempt->id);
        $this->gradingPosition = $index === false ? null : $index + 1;
        $this->gradingPrevId = ($index !== false && $index > 0) ? $orderedIds[$index - 1] : null;
        $this->gradingNextId = ($index !== false && $index < $orderedIds->count() - 1) ? $orderedIds[$index + 1] : null;

        // The dialog node is stable across morphs, so visibility is driven
        // imperatively. The event fires after the DOM update.
        $this->dispatch('grading-dialog-open');
    }

    /**
 * Accept every pending proposal of the attempt being graded.
 *
 * applyManualMarks replaces the whole block_scores array on purpose, so this
 * rebuilds the complete map from the current values plus the proposals. That is
 * also why the unit here is the attempt: a single block cannot be accepted alone.
 */
    public function acceptSuggestions()
    {
        $this->authorize('grade', $this->evaluation);

        $attempt = EvaluationAttempt::whereKey($this->gradingAttemptId)->firstOrFail();

        abort_unless($attempt->online_evaluation_id === $this->evaluation->id, 404);

        $pending = $attempt->gradingSuggestions()->pending()->get();

        if ($pending->isEmpty()) {
            $this->info('No hay propuestas pendientes para este intento.');

            return;
        }

        $scores = $attempt->block_scores ?? [];

        foreach ($pending as $suggestion) {
            $scores[$suggestion->block_id] = (float) $suggestion->total_points;
        }

        $graded = app(AttemptGrader::class)->applyManualMarks($attempt, $scores, $attempt->feedback);

        foreach ($pending as $suggestion) {
            $suggestion->update([
                'state' => GradingSuggestionState::Accepted->value,
                'final_points' => (float) $suggestion->total_points,
                'accepted_at' => now(),
                'accepted_by' => auth()->id(),
            ]);
        }

        $this->manualScores = $graded->block_scores;
        $this->gradingAttempt = $graded->fresh(['user', 'gradingSuggestions']);
        $this->suggestionRejectionReason = null;

        $this->success(sprintf(
            '%d propuesta%s aceptada%s. La nota del intento quedó en %s.',
            $pending->count(),
            $pending->count() === 1 ? '' : 's',
            $pending->count() === 1 ? '' : 's',
            number_format((float) $graded->score, 2),
        ));
    }

    /**
     * Copy the proposal into the inputs and let the teacher finish the job. The
     * numbers are editable on purpose: adjusting is the normal case, not an
     * exception.
     */
    public function prefillSuggestions()
    {
        $this->authorize('grade', $this->evaluation);

        $attempt = EvaluationAttempt::whereKey($this->gradingAttemptId)->firstOrFail();

        abort_unless($attempt->online_evaluation_id === $this->evaluation->id, 404);

        $suggestions = $attempt->gradingSuggestions()->pending()->get()
            ->keyBy('block_id');

        if ($suggestions->isEmpty()) {
            $this->info('No hay propuestas pendientes para este intento.');

            return;
        }

        $scores = $this->manualScores;

        foreach ($suggestions as $blockId => $suggestion) {
            $scores[$blockId] = (float) $suggestion->total_points;
        }

        $this->manualScores = $scores;

        $this->info('Notas sugeridas cargadas. Revisalas, ajustá lo que quieras y guardá.');
    }

    public function rejectSuggestions()
    {
        $this->authorize('grade', $this->evaluation);

        $reason = (string) ($this->suggestionRejectionReason ?: '');

        $attempt = EvaluationAttempt::whereKey($this->gradingAttemptId)->firstOrFail();

        abort_unless($attempt->online_evaluation_id === $this->evaluation->id, 404);

        $pending = $attempt->gradingSuggestions()->pending()->get();

        if ($pending->isEmpty()) {
            $this->info('No hay propuestas pendientes para este intento.');

            return;
        }

        foreach ($pending as $suggestion) {
            $suggestion->update([
                'state' => GradingSuggestionState::Rejected->value,
                'disagreement_reason' => $reason !== '' ? $reason : null,
                'accepted_at' => null,
                'accepted_by' => null,
            ]);
        }

        $this->gradingAttempt = $attempt->fresh(['user', 'gradingSuggestions']);
        $this->suggestionRejectionReason = null;

        $this->info(sprintf(
            '%d propuesta%s rechazada%s. El intento sigue esperando tu corrección.',
            $pending->count(),
            $pending->count() === 1 ? '' : 's',
            $pending->count() === 1 ? '' : 's',
        ));
    }

    public function closeGrading()
    {
        $this->reset(['gradingAttemptId', 'gradingAttempt', 'manualScores', 'feedback', 'suggestionRejectionReason', 'gradingPrevId', 'gradingNextId', 'gradingPosition', 'gradingTotal']);
        $this->dispatch('grading-dialog-close');
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

        // Settle the pending proposals against what the teacher actually saved:
        // identical numbers count as accepted, anything else as edited. A block
        // the teacher left empty stays undecided on purpose, since there is no
        // final mark to compare the proposal against.
        foreach ($attempt->gradingSuggestions()->pending()->get() as $suggestion) {
            if (! array_key_exists($suggestion->block_id, $this->manualScores) || $this->manualScores[$suggestion->block_id] === null) {
                continue;
            }

            $saved = (float) $this->manualScores[$suggestion->block_id];
            $same = abs($saved - (float) $suggestion->total_points) < 0.005;

            $suggestion->update([
                'state' => $same ? GradingSuggestionState::Accepted->value : GradingSuggestionState::Edited->value,
                'final_points' => $saved,
                'accepted_at' => now(),
                'accepted_by' => auth()->id(),
            ]);
        }

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

    /**
     * Open the assisted grading dialog.
     *
     * The markdown is deliberately not built here: with a full cohort the file
     * runs to hundreds of kilobytes and most teachers never open the dialog.
     */
    public function openAssist()
    {
        $this->authorize('exportGrading', $this->evaluation);

        $this->assistMode = GradingFileBuilder::MODE_GRADING;
        $this->assistAnonymize = false;
        $this->assistBlockId = $this->firstBlockWithoutRubric();
        $this->reset([
            'assistMarkdown', 'assistSummary', 'assistPasted', 'assistPreview',
            'assistErrors', 'assistWarnings', 'assistApplied', 'assistRubricApplied',
            'assistRubricRows', 'assistMarkRows',
        ]);
        $this->showAssistModal = true;
    }

    public function buildAssistMarkdown()
    {
        $this->authorize('exportGrading', $this->evaluation);

        $attempts = $this->assistAttempts();

        if ($attempts->isEmpty()) {
            $this->error('No hay intentos esperando corrección.');

            return;
        }

        $builder = new GradingFileBuilder(
            evaluation: $this->evaluation,
            attempts: $attempts,
            mode: $this->assistMode,
            anonymize: (bool) $this->assistAnonymize,
            blockId: $this->assistBlockId ?: null,
        );

        $markdown = $builder->build();

        $this->assistMarkdown = $markdown;
        $this->assistSummary = [
            'attempts' => $attempts->count(),
            // Counted by the builder itself, so the summary can never claim a
            // question the file does not carry.
            'blocks' => count($builder->gradableBlocks()),
            'kb' => round(strlen($markdown) / 1024, 1),
            'has_rubric' => $this->assistHasRubric(),
        ];

        // Anything already pasted referred to a different file.
        if ($this->assistPasted !== null) {
            $this->assistPasted = null;
            $this->assistPreview = [];
        }
    }

    public function downloadAssistMarkdown()
    {
        $this->authorize('exportGrading', $this->evaluation);

        if ($this->assistMarkdown === null) {
            $this->error('Primero generá el archivo.');

            return;
        }

        $markdown = $this->assistMarkdown;

        return response()->streamDownload(function () use ($markdown) {
            echo $markdown;
        }, GradingFileBuilder::filename($this->evaluation), ['Content-Type' => 'text/markdown; charset=utf-8']);
    }

    /**
     * Parse what was pasted and check it against the cohort. Writes nothing: the
     * rows only become suggestions once a teacher accepts them.
     */
    public function reviewPasted()
    {
        $this->authorize('exportGrading', $this->evaluation);

        $parsed = GradingFileParser::parse((string) $this->assistPasted);
        $messages = $parsed->errorMessages();

        if ($parsed->evaluationId() !== null && $parsed->evaluationId() !== $this->evaluation->id) {
            $messages[] = sprintf(
                'El archivo dice ser de la evaluación %d, no de la %d.',
                $parsed->evaluationId(),
                $this->evaluation->id,
            );
        }

        $attempts = $this->assistAttempts()->keyBy('id');
        $rows = [];

        foreach ($parsed->markRows as $index => $mark) {
            $attempt = $attempts->get((int) $mark['ref']);
            $blockPoints = (float) ($this->assistBlock($mark['block'])['points'] ?? 0);

            $status = 'ok';
            $detail = '';

            if ($attempt === null) {
                $status = 'desconocido';
                $detail = 'No hay ningún intento con ese ref en esta evaluación.';
            } elseif (! array_key_exists($mark['block'], $this->assistBlocks())) {
                $status = 'error';
                $detail = 'La pregunta no existe.';
            } elseif ($mark['points'] < 0 || $mark['points'] > $blockPoints) {
                $status = 'error';
                $detail = sprintf('Fuera de rango: la pregunta vale %.2f.', $blockPoints);
            } elseif ($this->alreadyMarked($attempt, $mark['block'])) {
                $status = 'sobrescribe';
                $detail = 'Ya tiene una nota cargada a mano.';
            }

            $rows[] = [
                'line' => $index + 1,
                'ref' => $mark['ref'],
                'student' => $attempt?->user?->fullname ?? '—',
                'block' => $mark['block'],
                'key' => $mark['key'] ?? '—',
                'points' => $mark['points'],
                'max' => $blockPoints,
                'flags' => array_map(fn ($flag) => $flag->value, $mark['flags']),
                'justification' => $mark['justification'],
                'status' => $status,
                'detail' => $detail,
            ];
        }

        $this->assistErrors = $messages;
        $this->assistWarnings = array_column($parsed->warnings, 'message');
        $this->assistPreview = $rows;

        if ($rows === []) {
            $this->warning('No se encontró ninguna nota para revisar.');
        }
    }

    /**
     * Editing after a review invalidates it: the rows on screen describe an older
     * text.
     */
    public function updatedAssistPasted()
    {
        // Cheap structural read, not a review: it only decides which save buttons
        // are worth offering. The rows on screen still describe an older text.
        $parsed = GradingFileParser::parse((string) $this->assistPasted);

        $this->assistRubricRows = count($parsed->rubricRows);
        $this->assistMarkRows = count($parsed->markRows);

        if ($this->assistPreview !== [] || $this->assistErrors !== []) {
            $this->assistPreview = [];
            $this->assistErrors = [];
        }
    }

    /**
 * Turn the reviewed rows into proposals.
 *
 * Only proposals: no attempt is marked here. The teacher still has to accept each
 * one, and that is the step that goes through AttemptGrader.
 */
    public function applyPastedSuggestions()
    {
        $this->authorize('exportGrading', $this->evaluation);

        $parsed = $this->parsePasted();

        if ($parsed === null) {
            return;
        }

        $report = app(GradingSuggestionImporter::class)->import($this->evaluation, $parsed);

        $this->assistErrors = array_merge($this->assistErrors, $report['rejected']);
        $this->assistApplied = $report;
        $this->assistPreview = [];

        if ($report['created'] > 0 || $report['updated'] > 0) {
            $this->success(sprintf(
                '%d propuesta%s guardada%s. Ahora las revisás una por una.',
                $report['created'] + $report['updated'],
                ($report['created'] + $report['updated']) === 1 ? '' : 's',
                ($report['created'] + $report['updated']) === 1 ? '' : 's',
            ));
        } elseif ($report['unchanged'] > 0) {
            $this->info('Las propuestas ya estaban guardadas. No cambió nada.');
        } else {
            $this->warning('No se encontró ninguna nota para guardar.');
        }
    }

    /**
     * Adopt a drafted rubric.
     *
     * Deliberately a separate action from importing marks: a rubric becomes the
     * promise every later mark is measured against, so the teacher has to choose
     * it deliberately rather than inherit it from the paste.
     */
    public function applyPastedRubric()
    {
        $this->authorize('update', $this->evaluation);

        $parsed = $this->parsePasted();

        if ($parsed === null) {
            return;
        }

        $report = app(GradingSuggestionImporter::class)->applyRubric($this->evaluation, $parsed);

        $this->assistErrors = array_merge($this->assistErrors, $report['rejected']);
        $this->assistRubricApplied = $report;

        if ($report['created'] > 0) {
            $this->success('Rúbrica guardada en la evaluación.');
        } elseif ($report['unchanged'] > 0) {
            $this->info('La rúbrica ya era la misma.');
        } else {
            $this->warning('No se encontró ninguna rúbrica para guardar.');
        }
    }

    /**
     * Re-read the pasted text, refusing when it no longer parses.
     */
    private function parsePasted(): ?ParsedGradingFile
    {
        $parsed = GradingFileParser::parse((string) $this->assistPasted);

        if (! $parsed->isValid()) {
            $this->assistErrors = $parsed->errorMessages();
            $this->error('El texto pegado tiene errores. Revisá la lista antes de continuar.');

            return null;
        }

        if ($parsed->evaluationId() !== null && $parsed->evaluationId() !== $this->evaluation->id) {
            $this->assistErrors = [sprintf(
                'El archivo dice ser de la evaluación %d, no de la %d.',
                $parsed->evaluationId(),
                $this->evaluation->id,
            )];

            return null;
        }

        return $parsed;
    }

    public function closeAssist()
    {
        $this->reset([
            'showAssistModal', 'assistMarkdown', 'assistSummary', 'assistPasted',
            'assistPreview', 'assistErrors', 'assistWarnings', 'assistBlockId',
'assistApplied', 'assistRubricApplied',
            'assistRubricRows', 'assistMarkRows',
        ]);
    }

    #[Computed]
    public function assistBlockOptions(): array
    {
        $rubrics = Rubric::snapshotFor($this->assistContent());

        // MaryUI reads `id` / `name` out of each option, so this has to be a
        // list of rows rather than a keyed array.
        return collect($this->assistBlocks())
            ->map(fn (array $block, string $id) => [
                'id' => $id,
                'name' => sprintf(
                    '%s — %s pts%s',
                    $id,
                    rtrim(rtrim(number_format((float) ($block['points'] ?? 0), 2, ',', ''), '0'), ',') ?: '0',
                    array_key_exists($id, $rubrics) ? '' : ' — sin rúbrica',
                ),
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function assistPendingCount(): int
    {
        return EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->where('status', \App\Enums\AttemptStatus::Submitted->value)
            ->count();
    }

    /**
 * Whether a block already carries a mark a teacher entered.
 *
 * A stored zero is not treated as a mark: every open_text block is stored as zero
 * by the auto-grader, so only a positive score, or a finished attempt, means there
 * is something real to overwrite.
 */
    private function alreadyMarked(EvaluationAttempt $attempt, string $block): bool
    {
        if ($attempt->status !== \App\Enums\AttemptStatus::Submitted) {
            return true;
        }

        return (float) ($attempt->block_scores[$block] ?? 0) > 0;
    }

    private function assistAttempts()
    {
        return EvaluationAttempt::with('user')
            ->where('online_evaluation_id', $this->evaluation->id)
            ->where('status', \App\Enums\AttemptStatus::Submitted->value)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function assistBlocks(): array
    {
        return GradingFileBuilder::gradableBlocksIn($this->assistContent());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function assistBlock(string $id): ?array
    {
        return $this->assistBlocks()[$id] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function assistContent(): array
    {
        return $this->assistSourceSnapshot() ?? $this->evaluation->content ?? [];
    }

    /**
     * The export describes the questions students were served, not the current
     * content, so it reads the snapshot of the cohort it will send.
     *
     * @return array<string, mixed>|null
     */
    private function assistSourceSnapshot(): ?array
    {
        return EvaluationAttempt::where('online_evaluation_id', $this->evaluation->id)
            ->where('status', \App\Enums\AttemptStatus::Submitted->value)
            ->whereNotNull('content_snapshot')
            ->orderBy('id')
            ->value('content_snapshot');
    }

    private function assistHasRubric(): bool
    {
        $blocks = $this->assistBlockId ? [$this->assistBlockId => true] : $this->assistBlocks();
        $rubrics = Rubric::snapshotFor($this->assistContent());

        return array_intersect_key($rubrics, $blocks) !== [];
    }

    /**
     * Drafting the rubric for a question that has none is the next thing worth
     * doing, so that is what the dialog opens on.
     */
    private function firstBlockWithoutRubric(): ?string
    {
        $rubrics = Rubric::snapshotFor($this->assistContent());

        foreach ($this->assistBlocks() as $id => $block) {
            if (! array_key_exists($id, $rubrics)) {
                return $id;
            }
        }

        return array_key_first($this->assistBlocks());
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
                @if($this->assistPendingCount() > 0)
                    <x-button
                        label="Corrección asistida · {{ $this->assistPendingCount() }}"
                        icon="o-cursor-arrow-rays"
                        class="btn-secondary"
                        wire:click="openAssist"
                        spinner="openAssist"
                    />
                @endif
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

    @if($this->isTeacher)
            <!-- KPIs Docente -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <x-stat
                    title="Alumnos corregidos"
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
    @endif

    @if($showAssistModal)
        <x-card title="Corrección asistida" class="mb-8 border-primary">
            <x-slot:menu>
                <x-button icon="o-x-mark" class="btn-ghost btn-sm" wire:click="closeAssist" />
            </x-slot:menu>

            <div class="space-y-6">
                {{-- Row 1: generate --}}
                <section class="space-y-4">
                    <h4 class="font-bold flex items-center gap-2">
                        <span class="badge badge-primary badge-sm">1</span>
                        Generá el archivo
                    </h4>

                    <div class="flex flex-wrap items-end gap-4">
                        <x-select
                            label="Pregunta"
                            wire:model.live="assistBlockId"
                            :options="$this->assistBlockOptions()"
                            option-value="id"
                            option-label="name"
                            class="w-full sm:w-96"
                        />
                        <div class="w-full sm:w-72">
                            <div class="fieldset-legend mb-0.5 flex items-center gap-1">
                                Qué pedirle al asistente
                                <x-notice
                                    variant="info"
                                    title="Qué le pido en cada modo"
                                    text="«Rúbrica vigente + notas»: el archivo sale con la rúbrica ya cargada y las respuestas de todos los alumnos. El modelo no puede cambiar los criterios; solo dice cuántos puntos le da a cada criterio de cada respuesta. Usalo cuando la rúbrica ya está revisada.

«Solo redactar la rúbrica»: el archivo sale con las consignas pero sin rúbrica. El modelo propone los criterios y sus puntajes, que tienen que sumar lo que vale la pregunta. Usalo primero, cuando la pregunta todavía no tiene rúbrica.

El orden que funciona es primero la rúbrica y después las notas: sin criterios escritos por vos, las correcciones salen de medir contra una vara que nadie revisó."
                                />
                            </div>
                            <x-select
                                wire:model.live="assistMode"
                                :options="[
                                    ['id' => 'grading', 'alias' => 'Rúbrica vigente + notas'],
                                    ['id' => 'rubric', 'alias' => 'Solo redactar la rúbrica'],
                                ]"
                                option-value="id"
                                option-label="alias"
                            />
                        </div>
                        <label class="label cursor-pointer justify-start gap-2 pb-1">
                            <input type="checkbox" wire:model.live="assistAnonymize" class="checkbox checkbox-sm checkbox-primary" />
                            <span class="label-text text-sm">Anonimizar</span>
                            @if($assistAnonymize)
                                <x-notice
                                    variant="info"
                                    title="Anonimizado"
                                    text="Los nombres quedan fuera del archivo: vas a ver un código en lugar de quién es cada alumno. Vas a tener que reconocer vos a quién corresponde cada respuesta al leerlas."
                                />
                            @else
                                <x-notice
                                    variant="danger"
                                    title="Los nombres salen de la aplicación"
                                    text="El archivo se envía a un modelo externo con los nombres y apellidos de los alumnos. Si tu institución no lo permite, o si preferís no hacerlo, activá Anonimizar."
                                />
                            @endif
                        </label>
                    </div>

                    <x-button
                        label="Generar"
                        icon="o-bolt"
                        class="btn-primary btn-sm"
                        wire:click="buildAssistMarkdown"
                        spinner="buildAssistMarkdown"
                    />

                    @if($assistMarkdown !== null)
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="stats stats-horizontal bg-base-200">
                                <div class="stat py-2 px-4">
                                    <div class="stat-title text-xs">Intentos</div>
                                    <div class="stat-value text-lg">{{ $assistSummary['attempts'] }}</div>
                                </div>
                                <div class="stat py-2 px-4">
                                    <div class="stat-title text-xs">Preguntas</div>
                                    <div class="stat-value text-lg">{{ $assistSummary['blocks'] }}</div>
                                </div>
                                <div class="stat py-2 px-4">
                                    <div class="stat-title text-xs">Tamaño</div>
                                    <div class="stat-value text-lg">{{ $assistSummary['kb'] }}<span class="text-xs ml-1">KB</span></div>
                                </div>
                            </div>

                            @unless($assistSummary['has_rubric'])
                                <x-notice
                                    variant="warning"
                                    title="Esta pregunta no tiene rúbrica"
                                    text="Sin criterios, el asistente tendría que inventar la vara de cada corrección y los resultados no serían comparables entre alumnos. Conviene pedirle primero una rúbrica en modo «Solo redactar la rúbrica», revisarla, y recién después pedir las notas."
                                />
                            @endunless

                            <details class="text-xs">
                                <summary class="cursor-pointer text-base-content/70">Cómo funciona y qué le pido en cada modo</summary>

                                <div class="mt-3 space-y-3 text-base-content/70">
                                    <p class="font-semibold">El circuito</p>
                                    <ol class="list-decimal list-inside space-y-1">
                                        <li>Elegí la pregunta y generá el archivo.</li>
                                        <li>Copiá el markdown y pegalo en tu asistente.</li>
                                        <li>Pedile que complete las secciones 4 y 5 y que termine con un bloque de código markdown para que se copie literal.</li>
                                        <li>Volvé a la sección 2, pegá lo que devolvió y revisá la tabla.</li>
                                        <li>Guardá las propuestas y después aceptá o ajustá alumno por alumno desde la corrección.</li>
                                    </ol>

                                    <p class="font-semibold pt-1">Los dos modos no son equivalentes</p>

                                    <div class="space-y-2">
                                        <div class="rounded border border-base-300 p-2">
                                            <div class="font-semibold">Solo redactar la rúbrica</div>
                                            <p class="mt-1">
                                                El archivo sale con las consignas pero <strong>sin rúbrica</strong>. Dejás al modelo la barra con la que después se mide a cada alumno:
                                                te devuelve los criterios y los puntajes de cada uno.
                                            </p>
                                            <p class="mt-1 text-base-content/60">
                                                Sirve para <strong>preparar</strong> la corrección. No toca notas: solo escribe la rúbrica de la pregunta.
                                            </p>
                                        </div>

                                        <div class="rounded border border-base-300 p-2">
                                            <div class="font-semibold">Rúbrica vigente + notas</div>
                                            <p class="mt-1">
                                                El archivo sale <strong>con la rúbrica ya cargada</strong> y con las respuestas de todos los alumnos.
                                                El modelo no puede cambiar los criterios: solo tiene que decir cuántos puntos le da a cada criterio de cada respuesta.
                                            </p>
                                            <p class="mt-1 text-base-content/60">
                                                Sirve para <strong>corriger</strong>. Guarda propuestas que después aprobás o ajustás vos.
                                            </p>
                                        </div>
                                    </div>

                                    <p class="pt-1">
                                        El orden que funciona es <strong>primero la rúbrica, después las notas</strong>:
                                        sin criterios escritos por vos, las correcciones salen de medir contra una vara que nadie revisó.
                                    </p>
                                </div>
                            </details>
                        </div>

                        {{-- Not bound with wire:model on purpose: copying happens in
                             the browser and the payload is large. --}}
                        <textarea
                            id="assist-markdown"
                            readonly
                            rows="10"
                            class="textarea textarea-bordered w-full font-mono text-xs"
                            style="white-space: pre; overflow-x: auto;"
                        >{{ $assistMarkdown }}</textarea>

                        <div class="flex flex-wrap items-center gap-2">
                            {{-- data-assist-copy is handled by resources/js/assist-grading.js
                                 through event delegation, so it works on first paint
                                 and survives any re-render. --}}
                            <x-button
                                label="Copiar"
                                icon="o-clipboard-document"
                                class="btn-sm btn-primary"
                                data-assist-copy
                            />
                            <span data-assist-feedback="copied" class="hidden text-success text-xs font-semibold">
                                ¡Copiado!
                            </span>
                            <span data-assist-feedback="failed" class="hidden text-error text-xs">
                                No se pudo copiar: el texto quedó seleccionado, copialo con Ctrl+C.
                            </span>
                            <x-button
                                label="Descargar .md"
                                icon="o-arrow-down-tray"
                                class="btn-sm btn-ghost"
                                wire:click="downloadAssistMarkdown"
                            />
                        </div>
                    @endif
                </section>

                <div class="border-t border-base-300"></div>

                {{-- Row 2: paste and review --}}
                <section class="space-y-4">
                    <h4 class="font-bold flex items-center gap-2">
                        <span class="badge badge-primary badge-sm">2</span>
                        Pegá y revisá
                        <x-notice
                            variant="info"
                            title="Esto todavía no guarda nada"
                            text="Pegar y revisar solo te muestra qué se leería al archivo, con los errores que tuviera y cuántas propuestas quedarían pendientes. Las notas no se escriben acá: las aprobás vos, una por una, desde la corrección de cada alumno."
                        />
                    </h4>

                    <x-textarea
                        wire:model="assistPasted"
                        label="Respuesta del asistente"
                        rows="10"
                        placeholder="Pegá acá el bloque markdown que devolvió..."
                        class="font-mono text-xs"
                    />

                    <div class="flex flex-wrap items-center gap-2">
                        <x-button
                            label="Revisar"
                            icon="o-magnifying-glass"
                            class="btn-sm btn-secondary"
                            wire:click="reviewPasted"
                            spinner="reviewPasted"
                        />

                        @if($assistRubricRows > 0)
                            <x-button
                                label="Guardar rúbrica ({{ $assistRubricRows }})"
                                icon="o-list-bullet"
                                class="btn-sm btn-ghost"
                                wire:click="applyPastedRubric"
                                spinner="applyPastedRubric"
                            />
                            <x-notice
                                variant="warning"
                                title="Guardar la rúbrica cambia cómo se corrige"
                                text="La rúbrica pasa a ser la referencia de la pregunta para siempre, y de ahí en adelante todas las correcciones se miden contra ella, no contra tu criterio libre. Guardala solo si la leíste y te sirve; si algo no te cierra, cambiala en el editor antes de usarla."
                            />
                        @endif

                        @if($assistMarkRows > 0)
                            <x-button
                                label="Guardar propuestas ({{ $assistMarkRows }})"
                                icon="o-inbox-arrow-down"
                                class="btn-sm btn-primary"
                                wire:click="applyPastedSuggestions"
                                spinner="applyPastedSuggestions"
                            />
                        @endif
                    </div>

                    @if($assistErrors !== [])
                        <div class="alert alert-error py-2 text-xs">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($assistErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($assistWarnings !== [])
                        <div class="alert alert-warning py-2 text-xs">
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach($assistWarnings as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($assistPreview !== [])
                        <div class="overflow-x-auto">
                            <table class="table table-xs table-zebra">
                                <thead>
                                    <tr>
                                        <th>Alumno</th>
                                        <th>Pregunta</th>
                                        <th>Criterio</th>
                                        <th>Justificación</th>
                                        <th class="text-right">Ptos</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assistPreview as $row)
                                        @php
                                            $badge = match ($row['status']) {
                                                'ok' => 'badge-success',
                                                'sobrescribe' => 'badge-warning',
                                                default => 'badge-error',
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                {{ $row['student'] }}
                                                <div class="text-xs text-base-content/50">ref {{ $row['ref'] }}</div>
                                            </td>
                                            <td class="font-mono text-xs">{{ $row['block'] }}</td>
                                            <td class="font-mono text-xs">{{ $row['key'] }}</td>
                                            <td class="max-w-64">
                                                <div class="text-xs" title="{{ $row['justification'] }}">
                                                    {{ \Illuminate\Support\Str::limit($row['justification'], 90) }}
                                                </div>
                                            </td>
                                            <td class="text-right font-semibold whitespace-nowrap">
                                                {{ rtrim(rtrim(number_format($row['points'], 2, ',', ''), '0'), ',') }}
                                                <span class="text-xs font-normal text-base-content/50">/ {{ $row['max'] }}</span>
                                            </td>
                                            <td>
                                                <span class="badge badge-xs {{ $badge }}">{{ $row['status'] }}</span>
                                                @if($row['detail'])
                                                    <div class="text-xs text-base-content/60">{{ $row['detail'] }}</div>
                                                @endif
                                                @if($row['flags'] !== [])
                                                    <div class="mt-0.5">
                                                        @foreach($row['flags'] as $flag)
                                                            <span class="badge badge-outline badge-xs">{{ $flag }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @php
                            $readable = collect($assistPreview)->where('status', 'ok')->count();
                            $overwrites = collect($assistPreview)->where('status', 'sobrescribe')->count();
                            $broken = collect($assistPreview)->whereIn('status', ['error', 'desconocido'])->count();
                        @endphp
                        <div class="text-xs text-base-content/60">
                            {{ $readable }} {{ $readable === 1 ? 'legible' : 'legibles' }} ·
                            {{ $overwrites }} {{ $overwrites === 1 ? 'pisa' : 'pisan' }} una nota final ·
                            {{ $broken }} con problemas
                        </div>
                    @endif
                </section>
            </div>
        </x-card>
    @endif

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
        {{-- Native <dialog> renders in the top layer, so no transformed ancestor
            can trap it off-screen. The element itself is always rendered and
            never removed: only its content is conditional. That keeps the node
            stable across Livewire morphs, so open/close is driven imperatively
            by browser events (see resources/js/grading-dialog.js) instead of
            relying on Alpine init timing, which broke prev/next navigation.
            Escape closes immediately for feedback and then syncs Livewire, so
            server state and the visible dialog never disagree. --}}
        <dialog
            id="grading-dialog"
            class="modal"
            wire:key="grading-dialog"
            x-on:cancel.prevent="$el.close(); $wire.closeGrading()"
        >
        @if($gradingAttemptId && $gradingAttempt)
            @php
                $attemptToGrade = $gradingAttempt;
                $snapshotContent = $attemptToGrade->content_snapshot ?? $evaluation->content;
                $sections = $snapshotContent['sections'] ?? [];
                $rubrics = $attemptToGrade->rubric_snapshot ?? App\Services\Evaluations\Rubric::snapshotFor($snapshotContent);
            @endphp
                <div class="modal-box w-11/12 max-w-6xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center gap-2 mb-4">
                        @if($gradingPrevId)
                            <x-button icon="o-chevron-left" class="btn-ghost btn-sm" tooltip="Anterior" wire:click="openGrading({{ $gradingPrevId }})" spinner="openGrading" />
                        @else
                            <x-button icon="o-chevron-left" class="btn-ghost btn-sm" disabled />
                        @endif
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-lg truncate">Corrigiendo: {{ $attemptToGrade->user->fullname }} (Intento {{ $attemptToGrade->attempt_number }})</h3>
                            @if($gradingPosition && $gradingTotal)
                                <div class="text-sm text-base-content/60">{{ $gradingPosition }} de {{ $gradingTotal }}</div>
                            @endif
                        </div>
                        @if($gradingNextId)
                            <x-button icon="o-chevron-right" class="btn-ghost btn-sm" tooltip="Siguiente" wire:click="openGrading({{ $gradingNextId }})" spinner="openGrading" />
                        @else
                            <x-button icon="o-chevron-right" class="btn-ghost btn-sm" disabled />
                        @endif
                        <x-button icon="o-x-mark" class="btn-ghost btn-sm" tooltip="Cerrar" wire:click="closeGrading" />
                    </div>

                    <div class="space-y-8">
                    @foreach($sections as $sIndex => $section)
                        <div class="space-y-4">
                            <h3 class="font-bold text-lg border-b pb-2">{{ $section['title'] ?? 'Sección' }}</h3>
                            @foreach($section['blocks'] as $block)
                                @php 
                                    $bId = $block['id']; 
                                    $studentAnswer = $attemptToGrade->answers[$bId] ?? null;
                                    // Snapshot first: the teacher must mark against the criteria
                                    // the student was actually served, not a later revision.
                                    $rubric = isset($rubrics[$bId])
                                        ? App\Services\Evaluations\Rubric::make($rubrics[$bId])
                                        : App\Services\Evaluations\Rubric::fromBlock($block);
                                    $suggestion = $attemptToGrade->gradingSuggestions
                                        ->firstWhere('block_id', $bId);
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

                                    @if($suggestion)
                                        @php $settled = ! $suggestion->isPending(); @endphp
                                        <div class="mt-3 rounded border {{ $settled ? 'border-base-300' : 'border-primary' }} bg-base-100 p-3 text-sm">
                                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                                <span class="font-semibold">Propuesta del asistente</span>
                                                <span class="badge {{ $suggestion->state_badge }} badge-sm">{{ $suggestion->state_label }}</span>
                                                @if($suggestion->revision > 1)
                                                    <span class="badge badge-ghost badge-xs">rev {{ $suggestion->revision }}</span>
                                                @endif
                                                @foreach($suggestion->flagList() as $flag)
                                                    <span class="badge {{ $flag->badgeClass() }} badge-xs">{{ $flag->label() }}</span>
                                                @endforeach
                                            </div>

                                            <table class="table table-xs">
                                                <tbody>
                                                    @foreach($suggestion->criteria as $criterion)
                                                        <tr>
                                                            <td class="align-top">
                                                                {{ $criterion['label'] ?? $criterion['key'] }}
                                                                @if(! empty($criterion['justification']))
                                                                    <div class="mt-0.5 text-xs text-base-content/60">{{ $criterion['justification'] }}</div>
                                                                @endif
                                                            </td>
                                                            <td class="w-20 text-right align-top font-semibold whitespace-nowrap">
                                                                {{ rtrim(rtrim(number_format((float) $criterion['points_awarded'], 2, ',', ''), '0'), ',') }}
                                                                @if(! empty($criterion['points_max']))
                                                                    <span class="text-xs font-normal text-base-content/50">/ {{ rtrim(rtrim(number_format((float) $criterion['points_max'], 2, ',', ''), '0'), ',') }}</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td class="text-right font-semibold">Total</td>
                                                        <td class="text-right font-semibold whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $suggestion->total_points, 2, ',', ''), '0'), ',') }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>

                                            @if($settled && $suggestion->disagreement_reason)
                                                <div class="mt-2 text-xs text-base-content/60">
                                                    Motivo del rechazo: {{ $suggestion->disagreement_reason }}
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if($rubric)
                                        @php $rubricProblems = $rubric->problems((float) ($block['points'] ?? 1)); @endphp
                                        <div class="mt-3 rounded border border-base-300 bg-base-100 p-3 text-sm">
                                            <div class="mb-2 flex items-center gap-2">
                                                <span class="font-semibold">Rúbrica</span>
                                                <span class="badge badge-ghost badge-xs">v{{ $rubric->version }}</span>
                                                <span class="badge badge-ghost badge-xs">{{ $rubric->source === App\Services\Evaluations\Rubric::SOURCE_AGENT ? 'propuesta por agente' : 'definida por el docente' }}</span>
                                            </div>
                                            <table class="table table-xs">
                                                <tbody>
                                                    @foreach($rubric->criteria as $criterion)
                                                        <tr>
                                                            <td class="w-8 font-mono text-xs align-top">{{ $criterion['key'] }}</td>
                                                            <td class="align-top">
                                                                {{ $criterion['label'] }}
                                                                @foreach($criterion['levels'] as $score => $description)
                                                                    <div class="mt-1 text-xs text-base-content/60">{{ $score }} → {{ $description }}</div>
                                                                @endforeach
                                                            </td>
                                                            <td class="w-16 text-right align-top font-semibold whitespace-nowrap">{{ number_format($criterion['points'], 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="2" class="text-right font-semibold">Total</td>
                                                        <td class="text-right font-semibold whitespace-nowrap">{{ number_format($rubric->maxPoints(), 2) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                            @foreach($rubricProblems as $problem)
                                                <div class="mt-2 text-xs text-error">{{ $problem }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    @php $pendingSuggestions = $attemptToGrade->gradingSuggestions->filter->isPending(); @endphp

                    @if($pendingSuggestions->isNotEmpty())
                        <div class="rounded-lg border border-primary bg-base-100 p-4 space-y-3">
                            <div class="text-sm">
                                <strong>{{ $pendingSuggestions->count() }}</strong> propuesta{{ $pendingSuggestions->count() === 1 ? '' : 's' }} del asistente
                                para este alumno. Aceptarlas writes la nota final; ajustarlas te deja corregir los números a mano.
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <x-button label="Aceptar y finalizar" icon="o-check" class="btn-sm btn-primary" wire:click="acceptSuggestions" spinner="acceptSuggestions" />
                                <x-button label="Cargar para ajustar" icon="o-pencil-square" class="btn-sm btn-ghost" wire:click="prefillSuggestions" spinner="prefillSuggestions" />
                                <x-button label="Rechazar" icon="o-x-circle" class="btn-sm btn-ghost text-error" wire:click="rejectSuggestions" spinner="rejectSuggestions" />
                            </div>
                            <x-select
                                label="Motivo del rechazo (opcional, sirve para mejorar el prompt)"
                                wire:model="suggestionRejectionReason"
                                :options="[
                                    ['id' => '', 'alias' => 'Sin motivo'],
                                    ['id' => 'criterio_mal_aplicado', 'alias' => 'Criterio mal aplicado'],
                                    ['id' => 'respuesta_ambigua', 'alias' => 'Respuesta ambigua'],
                                    ['id' => 'el_modelo_se_equivoco', 'alias' => 'El modelo se equivocó'],
                                    ['id' => 'rubrica_inadecuada', 'alias' => 'La rúbrica no sirve'],
                                ]"
                                option-value="id"
                                option-label="alias"
                            />
                        </div>
                    @endif

                    <x-textarea wire:model="feedback" label="Feedback General" rows="3" />

                    <div class="text-right">
                        <x-button label="Guardar Corrección" icon="o-check" class="btn-primary" wire:click="saveGrading" />
                    </div>
                </div>
            </div>
    @endif
        </dialog>
            <x-card title="Detalle de Intentos por Estudiante">
                <x-table :headers="$this->attemptHeaders()" :rows="$this->attempts" with-pagination>
                    @scope('cell_user_id', $attempt)
                        {{-- Inline max-width: holds even if the CSS bundle predates this markup. --}}
                        <span class="font-bold block truncate" style="max-width:11rem" title="{{ $attempt->user->fullname }}">{{ $attempt->user->fullname }}</span>
                    @endscope

                    @scope('cell_status', $attempt)
                        <span class="badge whitespace-nowrap {{ $attempt->status_badge }}">{{ $attempt->status_label }}</span>
                    @endscope

                    @scope('cell_submitted_at', $attempt)
                        {{ $attempt->submitted_at ? $attempt->submitted_at->format('d/m H:i') : '—' }}
                    @endscope

                    @scope('cell_score', $attempt)
                        @if($attempt->isFinished())
                            <span class="font-bold text-lg {{ $attempt->score >= $this->evaluation->passing_score ? 'text-success' : 'text-error' }}">
                                {{ number_format((float) $attempt->score, 2) }}
                            </span>
                        @else
                            <span class="text-base-content/50">—</span>
                        @endif
                    @endscope

                    @scope('actions', $attempt)
                        @if($attempt->isFinished())
                            <div class="flex items-center justify-end gap-2">
                                @if(($attempt->pending_suggestions_count ?? 0) > 0)
                                    <span class="badge badge-primary badge-sm tooltip tooltip-left lg:tooltip-right" data-tip="Propuestas del asistente esperando revisión">{{ $attempt->pending_suggestions_count }}</span>
                                @endif
                                <x-button
                                    icon="o-pencil-square"
                                    class="btn-sm btn-ghost"
                                    wire:click="openGrading({{ $attempt->id }})"
                                    tooltip="Corregir"
                                />
                            </div>
                        @endif
                    @endscope

                    <x-slot:empty>
                        <div class="text-center py-8">
                            <x-icon name="o-inbox" class="w-12 h-12 mx-auto text-base-300 mb-3" />
                            <p class="font-semibold">Todavía no hay intentos</p>
                            <p class="text-sm text-base-content/70 mt-1">
                                Los intentos de los alumnos van a aparecer acá apenas entregaren.
                            </p>
                        </div>
                    </x-slot:empty>
                </x-table>
            </x-card>
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
                        <x-button label="Realizar nuevo intento" icon="o-play" link="{{ route('evaluations.take', $evaluation->id) }}" class="btn-primary w-full" />
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
                                @forelse($attempts as $attempt)
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
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-base-content/60">
                                            Todavía no entregaste esta evaluación.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        </div>
    @endif
</div>
