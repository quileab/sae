<?php

namespace App\Console\Commands;

use App\Enums\AttemptStatus;
use App\Models\EvaluationAttempt;
use App\Models\OnlineEvaluation;
use App\Services\Evaluations\GradingFileBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Writes the single markdown file an assistant grader fills in and returns.
 *
 * The app never calls a model: this command only produces the file, and
 * evaluations:import-grading only reads one back.
 */
class ExportGradingFile extends Command
{
    protected $signature = 'evaluations:export-grading
        {evaluation : Evaluation id}
        {--out= : Destination file; defaults to storage/app/evaluaciones}
        {--mode=grading : grading to fill the rubric and the marks, rubric to only draft rubrics}
        {--anonymize : Omit student names from the file}
        {--status=submitted : Attempt status to export}
        {--preview : Print the file instead of writing it}';

    protected $description = 'Export pending answers and the marking contract as one markdown file';

    public function handle(): int
    {
        $evaluation = OnlineEvaluation::find($this->argument('evaluation'));

        if ($evaluation === null) {
            $this->error('La evaluación no existe.');

            return self::FAILURE;
        }

        $mode = (string) $this->option('mode');

        if (! in_array($mode, [GradingFileBuilder::MODE_GRADING, GradingFileBuilder::MODE_RUBRIC], true)) {
            $this->error('--mode debe ser "grading" o "rubric".');

            return self::FAILURE;
        }

        $status = (string) $this->option('status');

        if (AttemptStatus::tryFrom($status) === null) {
            $this->error("--status debe ser un estado válido, no \"{$status}\".");

            return self::FAILURE;
        }

        $attempts = EvaluationAttempt::with('user')
            ->where('online_evaluation_id', $evaluation->id)
            ->where('status', $status)
            ->orderBy('id')
            ->get();

        if ($attempts->isEmpty()) {
            $this->warn('No hay intentos en estado "'.$status.'". No hay nada que exportar.');

            return self::SUCCESS;
        }

        $markdown = (new GradingFileBuilder(
            evaluation: $evaluation,
            attempts: $attempts,
            mode: $mode,
            anonymize: (bool) $this->option('anonymize'),
        ))->build();

        if ($this->option('preview')) {
            $this->line($markdown);

            return self::SUCCESS;
        }

        $path = $this->resolvePath($evaluation, $attempts->count(), (string) $this->option('out'));

        if (file_put_contents($path, $markdown) === false) {
            $this->error('No se pudo escribir '.$path);

            return self::FAILURE;
        }

        $this->info('Archivo de corrección generado.');
        $this->line('  '.$path);
        $this->line(sprintf('  %d intentos · modo %s%s', $attempts->count(), $mode, $this->option('anonymize') ? ' · anonimizado' : ''));
        $this->newLine();
        $this->line('  1. Abrí el archivo con tu asistente y pegale las secciones 4 y 5.');
        $this->line('  2. Volvé a la aplicación y usá "Pegar correcciones" para revisarlo.');

        return self::SUCCESS;
    }

    private function resolvePath(OnlineEvaluation $evaluation, int $attempts, ?string $out): string
    {
        if ($out !== null && $out !== '') {
            $path = Str::finish($out, '.md');
            $this->ensureDirectoryExists(dirname($path));

            return $path;
        }

        $directory = storage_path('app/evaluaciones');
        $this->ensureDirectoryExists($directory);

        $name = sprintf(
            'correccion-%s-%s-%02d-intentos.md',
            Str::slug($evaluation->title),
            now()->format('Ymd-Hi'),
            $attempts,
        );

        return $directory.DIRECTORY_SEPARATOR.$name;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
