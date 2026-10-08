<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The clipboard lives in resources/js, which PHP cannot exercise. What this locks
 * down is the DOM contract that module depends on: the ids and data attributes it
 * looks for, so renaming one side without the other fails here.
 */
class AssistClipboardContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_dialog_renders_the_hooks_the_clipboard_module_reads(): void
    {
        $career = Career::factory()->create(['id' => 11]);
        $subject = Subject::create(['id' => 9901, 'career_id' => $career->id, 'name' => 'Gestión']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->enrollments()->create(['subject_id' => $subject->id, 'status' => 'active']);

        $content = ['sections' => [['id' => 's1', 'title' => 'S', 'blocks' => [
            ['id' => 'q1', 'type' => 'open_text', 'prompt' => 'Explique.', 'points' => 1.5],
        ]]]];

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'content' => $content,
        ]);

        EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted->value,
            'started_at' => now(), 'submitted_at' => now(),
            'answers' => ['q1' => ['text' => 'Una respuesta.']],
            'content_snapshot' => $content,
            'rubric_snapshot' => Rubric::snapshotFor($content),
        ]);

        $this->actingAs($teacher);

        $html = Volt::test('evaluations.results', ['id' => $evaluation->id])
            ->call('openAssist')
            ->call('buildAssistMarkdown')
            ->html();

        // Source the module reads.
        $this->assertStringContainsString('id="assist-markdown"', $html);
        $this->assertStringContainsString('SAE:NOTAS:FIN', $html, 'the textarea must actually carry the file');

        // Trigger and both feedback slots, all delegatable from document.
        $this->assertStringContainsString('data-assist-copy', $html);
        $this->assertStringContainsString('data-assist-feedback="copied"', $html);
        $this->assertStringContainsString('data-assist-feedback="failed"', $html);

        // Feedback starts hidden: without Alpine, x-cloak does nothing and a
        // stray "¡Copiado!" would sit on screen permanently.
        $this->assertMatchesRegularExpression(
            '/data-assist-feedback="copied"[^>]*class="[^"]*\bhidden\b/',
            $html,
            'the copied message must ship hidden',
        );

        // The handler is a module now, not an inline script Livewire has to inject.
        $this->assertStringNotContainsString('copyAssistMarkdown', $html);
        $this->assertStringNotContainsString('navigator.clipboard', $html);
    }

    #[Test]
    public function the_module_is_imported_by_the_vite_entrypoint(): void
    {
        $this->assertStringContainsString(
            "import './assist-grading';",
            (string) file_get_contents(base_path('resources/js/app.js')),
            'the clipboard module must be part of the bundle or the button does nothing.',
        );
    }

    #[Test]
    public function the_module_reads_the_same_hooks_the_view_renders(): void
    {
        $module = (string) file_get_contents(base_path('resources/js/assist-grading.js'));

        $this->assertStringContainsString('#assist-markdown', $module);
        $this->assertStringContainsString('[data-assist-copy]', $module);
        $this->assertStringContainsString('data-assist-feedback="copied"', $module);
        $this->assertStringContainsString('data-assist-feedback="failed"', $module);

        // Without these the button would silently fail on http or older browsers.
        $this->assertStringContainsString('window.isSecureContext', $module);
        $this->assertStringContainsString('execCommand', $module);
    }
}
