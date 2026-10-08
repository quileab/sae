<?php

namespace Tests\Feature\Evaluations;

use App\Enums\AttemptStatus;
use App\Models\Career;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\GradingFileBuilder;
use App\Services\Evaluations\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExportGradingFileTest extends TestCase
{
    use RefreshDatabase;

    private OnlineEvaluation $evaluation;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 4401, 'career_id' => $career->id, 'name' => 'Gestión']);
        $this->evaluation = OnlineEvaluation::factory()->openFor()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'title' => 'Parcial 3 - Gestión',
            'content' => $this->content(),
        ]);
    }

    #[Test]
    public function it_writes_one_file_named_after_the_evaluation(): void
    {
        $this->submittedAttempt();
        $directory = storage_path('app/evaluaciones');
        $before = glob($directory.'/*.md') ?: [];

        $this->artisan('evaluations:export-grading', ['evaluation' => $this->evaluation->id])
            ->assertSuccessful();

        $written = array_values(array_diff(glob($directory.'/*.md') ?: [], $before));

        $this->assertCount(1, $written);
        $this->assertStringStartsWith('correccion-parcial-3-gestion-', basename($written[0]));
        $this->assertStringContainsString('-01-intentos.md', basename($written[0]));
        $this->assertStringContainsString('SAE id='.$this->evaluation->id, (string) file_get_contents($written[0]));

        @unlink($written[0]);
    }

    #[Test]
    public function it_writes_where_it_is_told(): void
    {
        $this->submittedAttempt();
        $target = storage_path('app/custom/salida');

        $this->artisan('evaluations:export-grading', [
            'evaluation' => $this->evaluation->id,
            '--out' => $target,
        ])->assertSuccessful();

        $this->assertFileExists($target.'.md');
    }

    #[Test]
    public function it_only_exports_the_status_it_is_asked_for(): void
    {
        $this->submittedAttempt();
        $this->gradedAttempt();

        $path = $this->runExport(status: AttemptStatus::Submitted->value);
        $this->assertStringContainsString('intentos=1', (string) file_get_contents($path));

        $path = $this->runExport(status: AttemptStatus::Graded->value, name: 'corregidas');
        $this->assertStringContainsString('intentos=1', (string) file_get_contents($path));
        $this->assertStringNotContainsString('intentos=2', (string) file_get_contents($path));
    }

    #[Test]
    public function preview_prints_the_file_without_creating_one(): void
    {
        $this->submittedAttempt();
        $before = glob(storage_path('app/evaluaciones/*.md')) ?: [];

        $this->artisan('evaluations:export-grading', [
            'evaluation' => $this->evaluation->id,
            '--preview' => true,
        ])
            ->expectsOutputToContain('# Corrección asistida — Parcial 3 - Gestión')
            ->assertSuccessful();

        $this->assertSame($before, glob(storage_path('app/evaluaciones/*.md')) ?: []);
    }

    #[Test]
    public function anonymising_reaches_the_file_on_disk(): void
    {
        $attempt = $this->submittedAttempt();

        $path = $this->runExport(anonymize: true);
        $contents = (string) file_get_contents($path);

        $this->assertStringNotContainsString($attempt->user->fullname, $contents);
        $this->assertStringContainsString('ref `'.str_pad((string) $attempt->id, 5, '0', STR_PAD_LEFT).'`', $contents);
    }

    #[Test]
    public function rubric_mode_reaches_the_file_on_disk(): void
    {
        $this->submittedAttempt();

        $path = $this->runExport(mode: GradingFileBuilder::MODE_RUBRIC);
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('modo=rubric', $contents);
        $this->assertStringNotContainsString('Distingue dato de información', $contents);
    }

    #[Test]
    public function an_unknown_evaluation_fails_cleanly(): void
    {
        $this->artisan('evaluations:export-grading', ['evaluation' => 999999])
            ->expectsOutputToContain('La evaluación no existe.')
            ->assertFailed();
    }

    #[Test]
    public function an_unknown_mode_is_rejected(): void
    {
        $this->artisan('evaluations:export-grading', [
            'evaluation' => $this->evaluation->id,
            '--mode' => 'inventado',
        ])
            ->expectsOutputToContain('--mode debe ser "grading" o "rubric".')
            ->assertFailed();
    }

    #[Test]
    public function an_unknown_status_is_rejected(): void
    {
        $this->artisan('evaluations:export-grading', [
            'evaluation' => $this->evaluation->id,
            '--status' => 'inventado',
        ])
            ->expectsOutputToContain('--status debe ser un estado válido')
            ->assertFailed();
    }

    #[Test]
    public function a_cohort_with_nothing_pending_is_not_an_error(): void
    {
        $this->artisan('evaluations:export-grading', ['evaluation' => $this->evaluation->id])
            ->expectsOutputToContain('No hay intentos en estado "submitted".')
            ->assertSuccessful();
    }

    /**
     * Every test writes to its own path: sharing the default directory would
     * make each assertion depend on files the previous tests left behind.
     */
    private function runExport(
        string $status = AttemptStatus::Submitted->value,
        ?string $mode = null,
        bool $anonymize = false,
        string $name = 'salida',
    ): string {
        $options = [
            'evaluation' => $this->evaluation->id,
            '--status' => $status,
            '--out' => storage_path('app/test-export/'.$name.'-'.uniqid()),
        ];

        if ($mode !== null) {
            $options['--mode'] = $mode;
        }

        if ($anonymize) {
            $options['--anonymize'] = true;
        }

        $this->artisan('evaluations:export-grading', $options)->assertSuccessful();

        $path = $options['--out'].'.md';
        $this->assertFileExists($path);

        return $path;
    }

    private function submittedAttempt(): EvaluationAttempt
    {
        return $this->attempt(AttemptStatus::Submitted);
    }

    private function gradedAttempt(): EvaluationAttempt
    {
        return $this->attempt(AttemptStatus::Graded);
    }

    private function attempt(AttemptStatus $status): EvaluationAttempt
    {
        return EvaluationAttempt::create([
            'user_id' => User::factory()->create(['role' => 'student', 'name' => 'Apellido, Nombre'])->id,
            'online_evaluation_id' => $this->evaluation->id,
            'attempt_number' => 1,
            'status' => $status->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'answers' => ['g1_1' => ['text' => 'El remito es un dato; el total mensual es información.']],
            'content_snapshot' => $this->evaluation->content,
            'rubric_snapshot' => Rubric::snapshotFor($this->evaluation->content),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(): array
    {
        return ['sections' => [[
            'id' => 'sec_1',
            'title' => 'Parte 1',
            'blocks' => [[
                'id' => 'g1_1',
                'type' => 'open_text',
                'prompt' => 'Explique la diferencia entre dato e información.',
                'points' => 1.5,
                'rubric' => [
                    'source' => 'agent',
                    'criteria' => [['key' => 'dato', 'label' => 'Distingue dato de información', 'points' => 1.5]],
                ],
            ]],
        ]]];
    }
}
