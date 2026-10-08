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

    #[Test]
    public function the_dialog_stacks_its_steps_in_rows_instead_of_columns(): void
    {
        $html = $this->renderDialog();

        // The two-column shell was the thing that squeezed the review table.
        $this->assertStringNotContainsString('lg:grid-cols-2', $html);
        $this->assertStringContainsString('border-t border-base-300', $html, 'rows are separated by a rule');

        // Row order must survive: generate, then paste.
        $this->assertLessThan(
            strpos($html, 'Pegá y revisá'),
            strpos($html, 'Generá el archivo'),
        );
    }

    #[Test]
    public function the_advisories_are_popups_that_still_carry_their_message(): void
    {
        // A paste carrying rubric rows is what reveals the last notice, so this
        // renders with one rather than asserting on the empty dialog.
        $html = $this->renderDialog(pasted: $this->pasteWithRubricRows());

        // Rendered as a tooltip trigger, not as an inline alert block.
        $this->assertStringNotContainsString('alert-warning py-2 text-xs', $html);
        $this->assertStringNotContainsString('alert-info py-2 text-xs', $html);

        $notices = [
            'modelo externo con los nombres de los alumnos' => 'tooltip-danger',
            'todavía no tiene rúbrica' => 'tooltip-warning',
            'Nada se guarda todavía' => 'tooltip-info',
            'referencia de toda la evaluación' => 'tooltip-warning',
        ];

        foreach ($notices as $text => $classes) {
            $this->assertStringContainsString(
                'data-tip="'.$this->tipFor($html, $text).'"',
                $html,
                sprintf('the "%s" notice must keep its message', $classes),
            );
        }

        // Every notice is a focusable trigger with an accessible name: DaisyUI only
        // reveals a tooltip on :hover, .tooltip-open or :has(:focus-visible), so
        // without focus the message would be unreachable by keyboard.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*aria-label="El archivo sale/',
            $html,
            'the notice trigger must be a focusable button carrying the message as its name',
        );
        $this->assertStringContainsString("classList.toggle('tooltip-open')", $html, 'click must reveal it too');
    }

    #[Test]
    public function parse_errors_stay_in_the_body_and_do_not_become_popups(): void
    {
        // A parser error is the result the teacher has to act on, so it must not
        // hide behind an icon.
        $rendered = $this->renderDialog(pasted: '<!-- SAE id=1 -->'."\n".'sin tablas'."\n");

        $this->assertStringContainsString('alert alert-error', $rendered);
        $this->assertStringContainsString('Falta el bloque', $rendered);
    }

    private function pasteWithRubricRows(): string
    {
        return implode('
', [
            '<!-- SAE id=1 modo=rubric -->',
            '',
            '<!-- SAE:RUBRICA:INICIO -->',
            '| bloque | variante | clave | criterio | puntaje_max | niveles |',
            '| --- | --- | --- | --- | --- | --- |',
            '| q2 | — | concepto | Explica el concepto. | 1.0 | 0: no |',
            '<!-- SAE:RUBRICA:FIN -->',
            '',
            '<!-- SAE:NOTAS:INICIO -->',
            '| ref | bloque | clave | puntaje | flags | justificacion |',
            '| --- | --- | --- | --- | --- | --- |',
            '<!-- SAE:NOTAS:FIN -->',
            '',
        ]);
    }

    private int $evaluationId = 0;

    /**
     * Reads the notice text back out of the rendered tooltip attribute.
     */
    private function tipFor(string $html, string $fragment): string
    {
        preg_match('/data-tip="([^"]*'.preg_quote($fragment, '/').'[^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches, sprintf('no notice contains "%s"', $fragment));

        return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }

    private function renderDialog(?string $pasted = null): string
    {
        $career = Career::factory()->create(['id' => 11]);
        $subject = Subject::create(['id' => 9911, 'career_id' => $career->id, 'name' => 'Gestión']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $teacher->enrollments()->create(['subject_id' => $subject->id, 'status' => 'active']);

        $content = ['sections' => [['id' => 's1', 'title' => 'S', 'blocks' => [
            ['id' => 'q1', 'type' => 'open_text', 'prompt' => 'Explique.', 'points' => 1.5],
            ['id' => 'q2', 'type' => 'open_text', 'prompt' => 'Sin rubrica.', 'points' => 1.0],
        ]]]];

        $evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'content' => $content,
        ]);
        $this->evaluationId = $evaluation->id;

        EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student'])->id,
            'online_evaluation_id' => $evaluation->id,
            'attempt_number' => 1,
            'status' => AttemptStatus::Submitted->value,
            'started_at' => now(), 'submitted_at' => now(),
            'answers' => ['q1' => ['text' => 'Una.'], 'q2' => ['text' => 'Otra.']],
            'content_snapshot' => $content,
            'rubric_snapshot' => Rubric::snapshotFor($content),
        ]);

        $this->actingAs($teacher);

        $component = Volt::test('evaluations.results', ['id' => $evaluation->id])
            ->call('openAssist')
            ->call('buildAssistMarkdown');

        if ($pasted !== null) {
            $component->set('assistPasted', $pasted)->call('reviewPasted');
        }

        return $component->html();
    }
}
