<?php

namespace App\Console\Commands;

use App\Enums\GradingFlag;
use App\Models\OnlineEvaluation;
use App\Services\Evaluations\GradingCalibration;
use Illuminate\Console\Command;

/**
 * Reports how much an assistant grader agrees with the teacher.
 *
 * Only settled suggestions enter the math; pending ones are listed so the
 * teacher knows to finish reviewing first. Small samples print a caution
 * instead of pretending to conclude anything.
 */
class CalibrateGrading extends Command
{
    protected $signature = 'evaluations:calibrate
        {evaluation : Evaluation id}
        {--agent= : Only measure suggestions produced by this agent}
        {--block= : Only measure this block id}
        {--min=10 : Settled suggestions below this count make the report unreliable}
        {--json : Print the report as JSON}';

    protected $description = 'Measure agreement between assistant proposals and final teacher marks';

    public function handle(): int
    {
        $evaluation = OnlineEvaluation::find($this->argument('evaluation'));

        if ($evaluation === null) {
            $this->error('La evaluación no existe.');

            return self::FAILURE;
        }

        $min = max(1, (int) $this->option('min'));

        $report = app(GradingCalibration::class)->report(
            $evaluation,
            $this->option('agent') !== null && $this->option('agent') !== '' ? (string) $this->option('agent') : null,
            $this->option('block') !== null && $this->option('block') !== '' ? (string) $this->option('block') : null,
            $min,
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->printReport($evaluation, $report);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function printReport(OnlineEvaluation $evaluation, array $report): void
    {
        $this->info(sprintf('Calibración — %s (#%d)', $evaluation->title, $evaluation->id));

        if ($report['agent'] !== null) {
            $this->line(sprintf('  Fuente: %s', $report['agent']));
        }

        if ($report['block'] !== null) {
            $this->line(sprintf('  Pregunta: %s', $report['block']));
        }

        if ($report['settled'] === 0) {
            $this->warn(sprintf(
                'No hay sugerencias liquidadas para medir%s. Revisá y aceptá, ajustá o rechazá primero.',
                $report['pending'] > 0 ? sprintf(' (%d todavía pendientes)', $report['pending']) : ''
            ));

            return;
        }

        $this->line(sprintf(
            'Liquidadas: %d (aceptadas %d, con ajustes %d, rechazadas %d) · pendientes: %d',
            $report['settled'],
            $report['accepted'],
            $report['edited'],
            $report['rejected'],
            $report['pending']
        ));

        $this->line(sprintf(
            'Aceptación: %s',
            $report['acceptance_rate'] === null ? '—' : number_format($report['acceptance_rate'] * 100, 1).'%'
        ));

        if ($report['measured'] === 0) {
            $this->line('Sin casos con nota final distinta: todo lo liquidado se aceptó tal cual.');
        } else {
            $this->line(sprintf(
                'Error medio (n=%d, editadas + rechazadas con nota): %s pts%s',
                $report['measured'],
                number_format((float) $report['mae'], 2),
                $report['mae_normalized'] === null ? '' : sprintf(' · %s%% de la pregunta', number_format($report['mae_normalized'] * 100, 1))
            ));

            $bias = (float) $report['bias'];

            if (abs($bias) >= 0.005) {
                $this->line(sprintf(
                    'Sesgo: %s%s (el modelo puntúa %s)',
                    $bias > 0 ? '+' : '',
                    number_format($bias, 2),
                    $bias > 0 ? 'alto' : 'bajo'
                ));
            }
        }

        if ($report['by_block'] !== []) {
            $this->newLine();
            $this->line('Por pregunta:');
            $this->table(
                ['Pregunta', 'Liquidadas', 'Aceptadas', 'MAE', 'n'],
                collect($report['by_block'])->map(fn (array $row, string $block) => [
                    $block,
                    $row['settled'],
                    $row['accepted'],
                    $row['mae'] === null ? '—' : number_format($row['mae'], 2),
                    $row['measured'],
                ])->values()->all()
            );
        }

        if (count($report['by_agent']) > 1) {
            $this->newLine();
            $this->line('Por fuente:');
            $this->table(
                ['Fuente', 'Liquidadas', 'Aceptadas', 'MAE', 'n'],
                collect($report['by_agent'])->map(fn (array $row, string $agent) => [
                    $agent,
                    $row['settled'],
                    $row['accepted'],
                    $row['mae'] === null ? '—' : number_format($row['mae'], 2),
                    $row['measured'],
                ])->values()->all()
            );
        }

        if ($report['flags'] !== []) {
            $this->newLine();
            $this->line('Banderas (cuando el modelo marcó esto, ¿cuántas veces el docente terminó rechazando?):');
            $this->table(
                ['Bandera', 'Casos', '% rechazadas'],
                collect($report['flags'])->map(fn (int $count, string $flag) => [
                    GradingFlag::tryFrom($flag)?->label() ?? $flag,
                    $count,
                    number_format(($report['flag_rejection_rate'][$flag] ?? 0) * 100, 1).'%',
                ])->values()->all()
            );
        }

        if ($report['disagreement_reasons'] !== []) {
            $this->newLine();
            $this->line('Motivos de desacuerdo:');
            $this->table(
                ['Motivo', 'Casos'],
                collect($report['disagreement_reasons'])->map(fn (int $count, string $reason) => [
                    $reason,
                    $count,
                ])->values()->all()
            );
        }

        if (! $report['reliable']) {
            $this->newLine();
            $this->warn(sprintf(
                'Muestra chica: %d liquidadas de %d mínimas. Tomá estos números como indicio, no como veredicto.',
                $report['settled'],
                $report['min_settled']
            ));
        }
    }
}
