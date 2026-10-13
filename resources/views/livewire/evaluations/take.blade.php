<?php

use App\Enums\AttemptStatus;
use App\Enums\EvaluationStatus;
use App\Models\OnlineEvaluation;
use App\Models\EvaluationAttempt;
use App\Services\Evaluations\AttemptGrader;
use App\Services\Evaluations\AttemptContent;
use App\Services\Evaluations\EvaluationContent;
use App\Services\Evaluations\Rubric;
use Livewire\Volt\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public ?OnlineEvaluation $evaluation = null;
    public ?EvaluationAttempt $attempt = null;

    public $answers = [];
    public $content = [];
    public $remainingSeconds = 0;
    public $isPreview = false;
    public $lastSavedAt = null;

    /**
     * Which alternative of a drawn group the preview is showing.
     *
     * Empty means "the combination a student would actually get", which is the
     * default: a teacher should first see the real paper, then walk the
     * alternatives to check that every variant is answerable and fair.
     */
    public $previewBlockId = '';

/**
     * Discard the preview and draw again, so the teacher can see that the
     * combinations really do vary.
     */
public function previewAgain(): void
    {
        $this->previewBlockId = '';
        $this->content = $this->previewContent();
    }

    /**
     * content is state rather than a computed property, because the student path
     * writes to it while answering, so the alternative being previewed has to be
     * applied to it explicitly.
     */
public function updatedPreviewBlockId(): void
    {
        if ($this->isPreview) {
            $this->content = $this->previewContent();
        }
    }

    public function mount($id)
    {
        $this->evaluation = OnlineEvaluation::findOrFail($id);
        $user = auth()->user();

        $this->isPreview = $user->hasAnyRole(['admin', 'teacher', 'principal', 'director']);

        if ($this->isPreview) {
            $this->authorize('preview', $this->evaluation);
        } else {
            $this->authorize('take', $this->evaluation);
        }

        if ($this->isPreview) {
            // Mock preview attempt
            $this->content = $this->previewContent();
            $totalSeconds = $this->evaluation->duration_minutes * 60;
            $this->remainingSeconds = $totalSeconds;
            $this->warning('Modo Vista Previa: Estás viendo la evaluación como estudiante, pero tus respuestas no se guardarán.');
            return;
        }

        $this->guardEvaluationStillOpen();

        // Handle Attempts
        $latestAttempt = EvaluationAttempt::where('user_id', $user->id)
            ->where('online_evaluation_id', $this->evaluation->id)
            ->orderByDesc('attempt_number')
            ->first();

        if ($latestAttempt && !$latestAttempt->isFinished()) {
            $this->attempt = $latestAttempt;
        } else {
            // Check if we can create a new attempt
            $attemptCount = $latestAttempt?->attempt_number ?? 0;
            if ($this->evaluation->max_attempts > 0 && $attemptCount >= $this->evaluation->max_attempts) {
                // Max attempts reached, redirect to results
                return $this->redirectRoute('evaluations.results', ['id' => $this->evaluation->id]);
            }
            // Each attempt draws its own questions from the groups that offer
            // alternatives, so a second attempt is not a rehearsal of the first.
            // The draw lands in the snapshot, which is what grading normalises
            // against and what the grading file exports.
            $served = AttemptContent::draw($this->evaluation->content);

            // Create new attempt
            $this->attempt = EvaluationAttempt::create([
                'user_id' => $user->id,
                'online_evaluation_id' => $this->evaluation->id,
                'attempt_number' => $attemptCount + 1,
                'status' => AttemptStatus::InProgress->value,
                'started_at' => now(),
                'answers' => [],
                'content_snapshot' => $served,
                // Frozen for the same reason as the content: a rubric edited after
                // submission must not reinterpret suggestions made against it.
                // Built from the served questions so the student is not measured
                // against rubrics for questions they were never shown.
                'rubric_snapshot' => Rubric::snapshotFor($served),
            ]);
        }

        if ($this->attempt->isFinished()) {
            return $this->redirectRoute('evaluations.results', ['id' => $this->evaluation->id]);
        }

        $this->answers = $this->attempt->answers ?? [];

        // The snapshot freezes what this student was shown, so editing the
        // evaluation later cannot change the questions mid-attempt.
        $snapshot = $this->attempt->content_snapshot ?? $this->evaluation->content;

        // Apply Shuffling and preserve structure
        if (! isset($this->answers['_metadata']['shuffled_content'])) {
            $content = $snapshot;
            if ($this->evaluation->shuffle_questions) {
                foreach ($content['sections'] ?? [] as &$section) {
                    if (isset($section['blocks'])) {
                        shuffle($section['blocks']);
                    }
                }
                unset($section);
            }
            $this->content = $content;
            $this->answers['_metadata'] = ['shuffled_content' => $content];
            $this->attempt->update(['answers' => $this->answers]);
        } else {
            $this->content = $this->answers['_metadata']['shuffled_content'];
        }

        // Calculate remaining time
        $elapsed = now()->diffInSeconds($this->attempt->started_at);
        $totalSeconds = $this->evaluation->duration_minutes * 60;
        $this->remainingSeconds = max(0, $totalSeconds - $elapsed);

        if ($this->remainingSeconds <= 0 && !$this->attempt->isFinished()) {
            $this->submitAttempt(); // Auto submit
        }
    }

    /**
     * The evaluation must still be inside its availability window.
     *
     * Re-checked on every action so a long-lived browser tab cannot start,
     * resume or submit after the deadline has passed.
     */
    protected function guardEvaluationStillOpen(): void
    {
        $evaluation = $this->evaluation;

        if ($evaluation->status !== EvaluationStatus::Published->value) {
            abort(403, 'Esta evaluación no está disponible.');
        }

        if ($evaluation->starts_at && now()->lt($evaluation->starts_at)) {
            abort(403, 'Esta evaluación todavía no está disponible.');
        }

        if ($evaluation->ends_at && now()->gt($evaluation->ends_at)) {
            abort(403, 'El plazo de entrega de esta evaluación ha finalizado.');
        }

        if (! $evaluation->enrollsUser(auth()->user())) {
            abort(403, 'No estás inscribed en esta evaluación.');
        }
    }

    /**
 * Every block, flattened, carrying its section title and answered flag.
 *
 * Used for the question navigator and the progress counters so the header and
 * the sidebar can never disagree with the answers array.
 *
 * @return array<int, array{block: array, section: string, intro: string, number: int, block_id: string, answered: bool}>
 */
/**
 * The form the preview shows: a real draw, with the chosen alternative swapped in.
 *
 * The swap is made by group, not by looking for the chosen id: after the draw the
 * chosen question is not in the form at all (that is the point of the feature), so
 * the slot it belongs to is the one its group was given.
 */
public function previewContent(): array
{
    $authored = $this->evaluation->content ?? [];
    $content = AttemptContent::draw($authored);

    if ($this->previewBlockId === '') {
        return $content;
    }

    $alternative = $this->previewBlock();
    $group = AttemptContent::groupOf($authored, $this->previewBlockId);

    if ($alternative === null || $group === null) {
        return $content;
    }

    foreach ($content['sections'] as $sIndex => $section) {
        if ((string) ($section['id'] ?? '') !== $group) {
            continue;
        }

        // Keeps the group in place and the form readable in group order.
        $content['sections'][$sIndex]['blocks'][0] = $alternative;

        break;
    }

    return $content;
}

/**
 * Every alternative a drawn group can serve, for the preview selector.
 *
 * @return array<string, string>
 */
public function previewOptions(): array
{
    $options = [];

    foreach (AttemptContent::variantGroups($this->evaluation->content ?? []) as $group) {
        foreach ($group['blocks'] as $block) {
            if (empty($block['id'])) {
                continue;
            }

            $options[$block['id']] = sprintf(
                '%s · %s · %s pts',
                $group['title'],
                Str::limit(trim(strip_tags($block['prompt'] ?? '(sin consigna)')), 70),
                EvaluationContent::formatPoints($block['points'] ?? 0),
            );
        }
    }

    return $options;
}

/**
 * The authored question behind the preview selection, with the group it came
 * from so the swap keeps that group's heading.
 */
public function previewBlock(): ?array
{
    foreach (AttemptContent::variantGroups($this->evaluation->content ?? []) as $group) {
        foreach ($group['blocks'] as $block) {
            if (($block['id'] ?? null) === $this->previewBlockId) {
                return $block;
            }
        }
    }

    return null;
}

#[Computed]
public function questionIndex(): array
{
    $index = [];
    $number = 0;

    foreach ($this->content['sections'] ?? [] as $sIndex => $section) {
        $title = $section['title'] ?? 'Sección '.($sIndex + 1);

        foreach ($section['blocks'] ?? [] as $block) {
            $number++;

            $index[] = [
                'block' => $block,
                'section' => $title,
                'intro' => $section['intro'] ?? '',
                'number' => $number,
                'block_id' => $block['id'] ?? (string) $number,
                'answered' => EvaluationContent::isAnswered($block, $this->answers[$block['id'] ?? ''] ?? null),
            ];
        }
    }

    return $index;
}

#[Computed]
public function totalBlocks(): int
{
    return count($this->questionIndex);
}

#[Computed]
public function answeredCount(): int
{
    return count(array_filter($this->questionIndex, fn (array $entry) => $entry['answered']));
}

public function saveProgress()
    {
        if ($this->isPreview) return; // Don't save to DB in preview
        if ($this->attempt?->isFinished()) return;

        $this->attempt->update(['answers' => $this->answers]);

        $this->lastSavedAt = now();
    }

    public function submitAttempt()
    {
        if ($this->isPreview) {
            $this->success('Vista previa finalizada. (No se guardaron datos).');
            return $this->redirectRoute('evaluations.index');
        }

        if ($this->attempt?->isFinished()) {
            return;
        }

        // A tab left open past the deadline must not be able to submit.
        if ($this->evaluation->ends_at && now()->gt($this->evaluation->ends_at)) {
            abort(403, 'El plazo de entrega de esta evaluación ha finalizado.');
        }

        $result = app(AttemptGrader::class)->submit($this->attempt, $this->answers);

        $this->success(
            $result['requires_manual']
                ? 'Evaluación entregada. Quedó pendiente la corrección del docente.'
                : 'Evaluación entregada correctamente.'
        );

        return $this->redirectRoute('evaluations.results', ['id' => $this->evaluation->id]);
    }
}
?>

<div x-data="{
        remaining: @entangle('remainingSeconds'),
        timer: null,
        autosave: null,
        init() {
            this.timer = setInterval(() => {
                if (this.remaining > 0) {
                    this.remaining--;
                } else {
                    clearInterval(this.timer);
                    $wire.submitAttempt();
                }
            }, 1000);

            // Persist debounced answers periodically in case the tab is closed
            // before the debounce window elapses.
            if (!@js($isPreview)) {
                this.autosave = setInterval(() => $wire.saveProgress(), 15000);
            }
        },
        formatTime(seconds) {
            const m = Math.floor(seconds / 60).toString().padStart(2, '0');
            const s = (seconds % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        }
    }"
    class="max-w-4xl mx-auto pb-10"
    x-init="window.addEventListener('beforeunload', (e) => { if (!@js($isPreview)) { $wire.saveProgress(); } })"
>
    <!-- Sticky toolbar: timer + progress stay visible while scrolling -->
    <div class="sticky top-0 z-30 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-base-200/80 backdrop-blur border-b border-base-300 mb-6">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary text-primary-content font-bold tabular-nums shadow-sm"
                     :class="{'bg-error text-error-content animate-pulse': remaining <= 300}">
                    <x-icon name="o-clock" class="w-4 h-4" />
                    <span class="font-mono text-lg" x-text="formatTime(remaining)"></span>
                </div>
                <div class="min-w-0">
                    <div class="font-bold truncate">{{ $evaluation->title }}</div>
                    <div class="text-xs text-base-content/60">{{ $evaluation->subject->name }}</div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                @unless($isPreview)
                    <span class="text-xs text-base-content/60 hidden sm:inline-flex items-center gap-1"
                          wire:loading.remove wire:target="saveProgress">
                        <x-icon name="o-cloud-arrow-up" class="w-3.5 h-3.5" />
                        Guardado
                    </span>

                    {{-- Horizontal navigator: keeps the full width for the questions --}}
                    <details class="dropdown dropdown-end">
                        <summary class="btn btn-sm btn-ghost gap-2 list-none">
                            <x-icon name="o-list-bullet" class="w-4 h-4" />
                            <span class="hidden sm:inline">Ir a la pregunta</span>
                            <span class="badge badge-sm badge-ghost tabular-nums">{{ $this->answeredCount }}/{{ $this->totalBlocks }}</span>
                        </summary>
                        <div class="dropdown-content z-40 mt-2 card bg-base-100 border border-base-200 shadow-lg max-h-[60vh] overflow-y-auto w-64">
                            <ul class="menu menu-sm p-2 gap-0.5">
                                @foreach($this->questionIndex as $entry)
                                    <li>
                                        <a href="#q-{{ $entry['block_id'] }}"
                                           class="flex items-center justify-between gap-2 {{ $entry['answered'] ? '' : 'opacity-60' }}">
                                            <span class="flex items-center gap-2 min-w-0">
                                                <span class="badge badge-xs shrink-0 tabular-nums {{ $entry['answered'] ? 'badge-success' : 'badge-ghost' }}">
                                                    {{ $entry['number'] }}
                                                </span>
                                                <span class="truncate">{{ $entry['section'] }}</span>
                                            </span>
                                            @if($entry['answered'])
                                                <x-icon name="o-check" class="w-3.5 h-3.5 text-success shrink-0" />
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
</details>
                @endunless
            </div>
        </div>

        @if($isPreview && $this->previewOptions())
            <div class="max-w-7xl mx-auto mt-3 flex flex-wrap items-center gap-2 text-xs">
                <x-alert icon="o-swatch" class="alert-info py-2">
                    Esta es la <strong>combinación real</strong> que recibiría un alumno: a cada grupo
                    le toca una de sus alternativas, sorteada al azar.
                </x-alert>
                <div class="flex items-center gap-2">
                    <x-select
                        wire:model.live="previewBlockId"
                        :options="$this->previewOptions()"
                        placeholder="Ver otra alternativa del grupo…"
                        class="select-sm min-w-64"
                    />
                    <x-button
                        wire:click="previewAgain"
                        label="Sortear otra"
                        icon="o-arrow-path"
                        class="btn-xs btn-ghost"
                        spinner="previewAgain"
                    />
                </div>
            </div>
        @endif

        @unless($isPreview)
            <progress class="progress progress-success w-full mt-2 h-1.5"
                      value="{{ $this->answeredCount }}"
                      max="{{ max($this->totalBlocks, 1) }}"></progress>
        @endunless
    </div>

    @if($evaluation->description)
        <div class="max-w-7xl mx-auto mb-6">
            <x-alert icon="o-information-circle" class="alert-info text-sm">
                {{ $evaluation->description }}
            </x-alert>
        </div>
    @endif

    @if($isPreview)
        <div class="max-w-7xl mx-auto mb-6">
            <x-alert icon="o-eye" class="alert-warning text-sm">
                <strong>Vista previa.</strong> Estás viendo la evaluación como la vería un alumno,
                pero tus respuestas no se guardan ni cuentan para ninguna nota.
            </x-alert>
        </div>
    @endif

    {{-- Questions always take the full available width: the relational design
         exercise draws tables side by side, and a sidebar column left them
         unusably narrow. Navigation lives in the sticky toolbar instead. --}}
    <div class="max-w-7xl mx-auto">
        {{-- Questions --}}
        <div class="space-y-6 pb-32">
            @forelse($this->questionIndex as $entry)
                @php $block = $entry['block']; @endphp

                @if($loop->first || $entry['section'] !== $this->questionIndex[$loop->index - 1]['section'])
                    <div class="pt-4 first:pt-0">
                        <h2 class="text-lg font-bold text-primary border-b-2 border-primary/20 pb-2">
                            {{ $entry['section'] }}
                        </h2>
                        @if(! empty($entry['intro']))
                            <p class="text-sm text-base-content/70 mt-2">{{ $entry['intro'] }}</p>
                        @endif
                    </div>
                @endif

                <x-evaluation-question
                    :block="$block"
                    :number="$entry['number']"
                    :answer="$answers[$block['id']] ?? null"
                />
            @empty
                <x-alert icon="o-exclamation-triangle" class="alert-warning">
                    Esta evaluación todavía no tiene preguntas cargadas.
                </x-alert>
            @endforelse
        </div>
    </div>

    {{-- Sticky submit bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-30 bg-base-100 border-t border-base-300 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-sm">
                @if($isPreview)
                    <span class="text-base-content/60">
                        Vista previa: la entrega no guarda nada.
                    </span>
                @else
                    <span class="text-base-content/70">
                        Respondiste <strong>{{ $this->answeredCount }}</strong> de <strong>{{ $this->totalBlocks }}</strong> preguntas.
                        @if($this->totalBlocks > 0 && $this->answeredCount < $this->totalBlocks)
                            <span class="text-warning">Te faltan {{ $this->totalBlocks - $this->answeredCount }}.</span>
                        @endif
                    </span>
                @endif
            </div>

            @if($isPreview)
                <x-button
                    label="Salir de la vista previa"
                    icon="o-arrow-left"
                    link="/evaluations"
                />
            @else
                <x-button
                    label="Entregar Evaluación"
                    icon="o-paper-airplane"
                    class="btn-primary px-8"
                    wire:click="submitAttempt"
                    wire:loading.attr="disabled"
                    spinner="submitAttempt"
                    wire:confirm="¿Confirmás la entrega definitiva? Después no vas a poder modificarla."
                />
            @endif
        </div>
    </div>
</div>
