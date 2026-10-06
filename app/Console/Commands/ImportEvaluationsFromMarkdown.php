<?php

namespace App\Console\Commands;

use App\Enums\EvaluationStatus;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Services\Evaluations\MarkdownImporter;
use Illuminate\Console\Command;

/**
 * Turns a Markdown question bank into an evaluation.
 *
 * Expected format:
 *   #### 1. Question text
 *   A. Option one
 *   B. Option two
 *   **Respuesta correcta: B**
 */
class ImportEvaluationsFromMarkdown extends Command
{
    protected $signature = 'evaluations:import-markdown
        {file : Path to the Markdown file to import}
        {--subject= : Subject id the evaluation belongs to}
        {--teacher= : User id of the owning teacher (defaults to the current subject teacher)}
        {--title= : Evaluation title (defaults to the file name)}
        {--duration=60 : Duration in minutes}
        {--status=draft : draft, published or closed}';

    protected $description = 'Create an online evaluation from a Markdown question bank';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->error("No se puede leer el archivo: {$path}");

            return self::FAILURE;
        }

        $subject = $this->resolveSubject();

        if ($subject === null) {
            return self::FAILURE;
        }

        $markdown = (string) file_get_contents($path);
        $sections = MarkdownImporter::parse($markdown);

        $blockCount = collect($sections)->sum(fn (array $section) => count($section['blocks'] ?? []));

        if ($blockCount === 0) {
            $this->error('No se detectaron preguntas. Revisá el formato del archivo.');

            return self::FAILURE;
        }

        $status = (string) $this->option('status');

        if (! EvaluationStatus::tryFrom($status)) {
            $this->error("Estado inválido: {$status}. Usá draft, published o closed.");

            return self::FAILURE;
        }

        $evaluation = OnlineEvaluation::create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->resolveTeacher($subject->id),
            'title' => $this->option('title') ?: pathinfo($path, PATHINFO_FILENAME),
            'description' => null,
            'duration_minutes' => (int) $this->option('duration'),
            'status' => $status,
            'content' => ['sections' => $sections],
        ]);

        $this->info(sprintf(
            'Evaluación creada (#%d "%s") con %d preguntas en %d sección/es.',
            $evaluation->id,
            $evaluation->title,
            $blockCount,
            count($sections),
        ));

        return self::SUCCESS;
    }

    private function resolveSubject(): ?Subject
    {
        $subjectId = $this->option('subject');

        if (! $subjectId) {
            $this->error('Falta --subject.');

            return null;
        }

        $subject = Subject::find($subjectId);

        if ($subject === null) {
            $this->error("La materia {$subjectId} no existe.");

            return null;
        }

        return $subject;
    }

    private function resolveTeacher(int $subjectId): int
    {
        $teacherId = $this->option('teacher');

        if ($teacherId) {
            return (int) $teacherId;
        }

        // Fall back to whoever teaches the subject.
        $teacher = Subject::find($subjectId)?->classSessions()->whereNotNull('teacher_id')->first()?->teacher_id;

        if (! $teacher) {
            $this->warn('No se pudo determinar el docente de la materia; se usará el usuario actual.');

            return (int) auth()->id();
        }

        return (int) $teacher;
    }
}
