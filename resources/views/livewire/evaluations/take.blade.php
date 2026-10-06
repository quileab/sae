<?php

use App\Enums\AttemptStatus;
use App\Enums\EvaluationStatus;
use App\Models\OnlineEvaluation;
use App\Models\EvaluationAttempt;
use App\Services\Evaluations\EvaluationContent;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Mary\Traits\Toast;

new #[Layout('layouts.app')] class extends Component {
    use Toast;

    public ?OnlineEvaluation $evaluation = null;
    public ?EvaluationAttempt $attempt = null;

    public $answers = [];
    public $content = [];
    public $remainingSeconds = 0;
    public $isPreview = false;
    public $lastSavedAt = null;

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
            $this->content = $this->evaluation->content;
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
            // Create new attempt
            $this->attempt = EvaluationAttempt::create([
                'user_id' => $user->id,
                'online_evaluation_id' => $this->evaluation->id,
                'attempt_number' => $attemptCount + 1,
                'status' => AttemptStatus::InProgress->value,
                'started_at' => now(),
                'answers' => [],
                'content_snapshot' => $this->evaluation->content,
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

        // Auto correct blocks
        $score = 0;
        $maxScore = EvaluationContent::getMaxScore($this->content ?? []);

        $blockScores = [];
        $requiresManual = false;

        $sections = $this->content['sections'] ?? [];
        foreach ($sections as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                $blockId = $block['id'];
                $answer = $this->answers[$blockId] ?? null;

                $result = EvaluationContent::gradeBlock($block, $answer);

                $blockScores[$blockId] = $result['score'];
                $score += $result['score'];

                if ($result['requires_manual']) {
                    $requiresManual = true;
                }
            }
        }

        // Normalize score to 10 based on maxScore
        $finalScore = $maxScore > 0 ? ($score / $maxScore) * 10 : 0;

        $this->attempt->update([
            'answers' => $this->answers,
            'status' => $requiresManual
                ? AttemptStatus::Submitted->value
                : AttemptStatus::Graded->value,
            'submitted_at' => now(),
            'score' => round($finalScore, 2),
            'block_scores' => $blockScores
        ]);

        $this->success('Evaluación entregada correctamente.');
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
    <!-- Header -->
    <div class="bg-base-100 rounded-xl shadow-sm p-6 md:p-8 mb-8 border border-base-200">
        <div class="flex justify-between items-start gap-4">
            <div>
                <div class="text-sm font-bold text-primary uppercase tracking-wider mb-2">{{ $evaluation->subject->name }}</div>
                <h1 class="text-2xl md:text-3xl font-extrabold">{{ $evaluation->title }}</h1>
            </div>
            <div class="shrink-0 flex flex-col items-end gap-2">
                <div class="flex items-center gap-2 bg-primary text-primary-content px-4 py-2 rounded-full shadow-lg border border-primary-content/20"
                     :class="{'bg-error text-error-content': remaining <= 300}">
                    <x-icon name="o-clock" class="w-5 h-5" />
                    <span class="font-mono text-xl font-bold tabular-nums" x-text="formatTime(remaining)"></span>
                </div>
                @unless($isPreview)
                    <span class="text-xs text-base-content/60 flex items-center gap-1" wire:loading.remove wire:target="saveProgress">
                        <x-icon name="o-cloud-arrow-up" class="w-3.5 h-3.5" />
                        Guardado
                    </span>
                @endunless
            </div>
        </div>

        @if($evaluation->description)
            <p class="text-base-content/80 mt-2">{{ $evaluation->description }}</p>
        @endif

        <div class="mt-4 flex flex-wrap gap-4 text-sm bg-base-200 p-3 rounded-lg">
            <div><span class="font-semibold opacity-70">Estudiante:</span> <span class="font-bold">{{ auth()->user()->fullname }}</span></div>
            <div><span class="font-semibold opacity-70">Tiempo total:</span> <span class="font-bold">{{ $evaluation->duration_minutes }} minutos</span></div>
            @if($evaluation->max_attempts > 0)
                <div><span class="font-semibold opacity-70">Intento:</span> <span class="font-bold">{{ $attempt?->attempt_number ?? 1 }} de {{ $evaluation->max_attempts }}</span></div>
            @endif
        </div>
    </div>

    <!-- Questions -->
    <div class="space-y-10">
        @php 
            $sections = $content['sections'] ?? [];
        @endphp
        
        @foreach($sections as $sIndex => $section)
            <div class="space-y-6">
                <div class="border-b-2 border-primary/20 pb-2">
                    <h2 class="text-xl font-bold text-primary">{{ $section['title'] ?? 'Sección ' . ($sIndex + 1) }}</h2>
                    @if(!empty($section['intro']))
                        <p class="text-sm text-base-content/70 mt-1">{{ $section['intro'] }}</p>
                    @endif
                </div>

                @foreach($section['blocks'] ?? [] as $index => $block)
                    <div class="bg-base-100 border border-base-200 rounded-xl shadow-sm p-6" wire:key="block-{{ $block['id'] }}">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="font-bold text-lg text-base-content leading-snug flex gap-2">
                                <span class="text-primary">{{ $index + 1 }}.</span> 
                                <span>{{ $block['prompt'] ?? 'Consigna' }}</span>
                            </h3>
                            <span class="badge badge-sm badge-ghost whitespace-nowrap">{{ $block['points'] ?? 1 }} pts</span>
                        </div>

                        <div class="mt-4">
                            @if($block['type'] === 'multiple_choice')
                                @include('livewire.evaluations.blocks.multiple_choice', ['block' => $block])
                            @elseif($block['type'] === 'short_text')
                                @include('livewire.evaluations.blocks.short_text', ['block' => $block])
                            @elseif($block['type'] === 'open_text')
                                @include('livewire.evaluations.blocks.open_text', ['block' => $block])
                            @elseif($block['type'] === 'table_design')
                                @include('livewire.evaluations.blocks.table_design', ['block' => $block])
                            @elseif($block['type'] === 'true_false')
                                @include('livewire.evaluations.blocks.true_false', ['block' => $block])
                            @elseif($block['type'] === 'checkboxes')
                                @include('livewire.evaluations.blocks.checkboxes', ['block' => $block])
                            @elseif($block['type'] === 'matching')
                                @include('livewire.evaluations.blocks.matching', ['block' => $block])
                            @else
                                <div class="alert alert-error">Tipo de bloque desconocido: {{ $block['type'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <!-- Submit Section -->
    <div class="mt-10 bg-base-100 border border-base-200 rounded-xl p-6 text-center shadow-sm">
        <h3 class="font-bold text-lg mb-2">¿Terminaste de responder?</h3>
        <p class="text-sm text-base-content/70 mb-6">Asegurate de haber revisado todas tus respuestas. Una vez entregada, no podrás modificarla.</p>
        <x-button label="Entregar Evaluación" icon="o-paper-airplane" class="btn-primary w-full sm:w-auto px-8" 
                  wire:click="submitAttempt" wire:confirm="¿Confirmas la entrega definitiva de la evaluación?" />
    </div>
</div>
