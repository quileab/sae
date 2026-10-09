<?php

namespace App\Services\Evaluations;

use App\Enums\AttemptStatus;
use App\Enums\GradingSuggestionState;
use App\Models\GradingSuggestion;
use App\Models\OnlineEvaluation;
use Illuminate\Support\Collection;

/**
 * Measures how much an assistant grader agrees with the teacher.
 *
 * A suggestion only enters the math once it is settled (accepted, edited or
 * rejected) and a final teacher mark exists for it. Accepted rows count toward
 * the acceptance rate and enter the error aggregates with zero error, which is
 * exactly what "the teacher confirmed it untouched" means. Pending rows are
 * reported, never measured.
 */
final class GradingCalibration
{
    public const MIN_SETTLED_DEFAULT = 10;

    public const UNKNOWN_AGENT = '(sin agente)';

    /**
     * @return array{
     *     evaluation_id: int,
     *     agent: ?string,
     *     block: ?string,
     *     min_settled: int,
     *     settled: int,
     *     pending: int,
     *     accepted: int,
     *     edited: int,
     *     rejected: int,
     *     acceptance_rate: ?float,
     *     measured: int,
     *     mae: ?float,
     *     mae_normalized: ?float,
     *     bias: ?float,
     *     by_block: array<string, array{settled: int, accepted: int, measured: int, mae: ?float}>,
     *     by_agent: array<string, array{settled: int, accepted: int, measured: int, mae: ?float}>,
     *     flags: array<string, int>,
     *     flag_rejection_rate: array<string, ?float>,
     *     disagreement_reasons: array<string, int>,
     *     reliable: bool,
     * }
     */
    public function report(OnlineEvaluation $evaluation, ?string $agent = null, ?string $block = null, int $minSettled = self::MIN_SETTLED_DEFAULT): array
    {
        $settledStates = [
            GradingSuggestionState::Accepted->value,
            GradingSuggestionState::Edited->value,
            GradingSuggestionState::Rejected->value,
        ];

        $base = GradingSuggestion::query()
            ->whereHas('attempt', fn ($query) => $query->where('online_evaluation_id', $evaluation->id))
            ->when($agent !== null, fn ($query) => $query->where('agent', $agent))
            ->when($block !== null, fn ($query) => $query->where('block_id', $block));

        $pending = (clone $base)->where('state', GradingSuggestionState::Proposed->value)->count();

        /** @var Collection<int, GradingSuggestion> $settled */
        $settled = (clone $base)->whereIn('state', $settledStates)->with('attempt')->get();

        $measured = [];
        $byBlock = [];
        $byAgent = [];
        $flags = [];
        $flagRejected = [];
        $reasons = [];

        $accepted = 0;
        $edited = 0;
        $rejected = 0;

        foreach ($settled as $suggestion) {
            match ($suggestion->state) {
                GradingSuggestionState::Accepted => $accepted++,
                GradingSuggestionState::Edited => $edited++,
                GradingSuggestionState::Rejected => $rejected++,
                default => null,
            };

            $final = $this->finalMark($suggestion);

            if ($final !== null) {
                $error = (float) $suggestion->total_points - $final;
                $max = (float) ($suggestion->max_points ?? 0);

                $measured[] = [
                    'block' => $suggestion->block_id,
                    'agent' => $suggestion->agent ?? self::UNKNOWN_AGENT,
                    'error' => $error,
                    'normalized' => $max > 0 ? $error / $max : null,
                ];
            }

            $this->bump($byBlock, $suggestion->block_id, $suggestion, $final !== null ? abs((float) $suggestion->total_points - $final) : null);
            $this->bump($byAgent, $suggestion->agent ?? self::UNKNOWN_AGENT, $suggestion, $final !== null ? abs((float) $suggestion->total_points - $final) : null);

            foreach ($suggestion->flagList() as $flag) {
                $flags[$flag->value] = ($flags[$flag->value] ?? 0) + 1;

                if ($suggestion->state === GradingSuggestionState::Rejected) {
                    $flagRejected[$flag->value] = ($flagRejected[$flag->value] ?? 0) + 1;
                }
            }

            if ($suggestion->disagreement_reason !== null && $suggestion->disagreement_reason !== '') {
                $reasons[$suggestion->disagreement_reason] = ($reasons[$suggestion->disagreement_reason] ?? 0) + 1;
            }
        }

        $settledCount = $settled->count();
        $measuredCount = count($measured);

        foreach ($byBlock as &$row) {
            $row['mae'] = $row['measured'] > 0 ? round($row['errors'] / $row['measured'], 2) : null;
            unset($row['errors']);
        }
        unset($row);

        foreach ($byAgent as &$row) {
            $row['mae'] = $row['measured'] > 0 ? round($row['errors'] / $row['measured'], 2) : null;
            unset($row['errors']);
        }
        unset($row);

        ksort($byBlock);
        ksort($byAgent);
        arsort($flags);
        arsort($reasons);

        $flagRejectionRate = [];
        foreach ($flags as $flag => $count) {
            $flagRejectionRate[$flag] = round(($flagRejected[$flag] ?? 0) / $count, 3);
        }

        return [
            'evaluation_id' => $evaluation->id,
            'agent' => $agent,
            'block' => $block,
            'min_settled' => $minSettled,
            'settled' => $settledCount,
            'pending' => $pending,
            'accepted' => $accepted,
            'edited' => $edited,
            'rejected' => $rejected,
            'acceptance_rate' => $settledCount > 0 ? round($accepted / $settledCount, 3) : null,
            'measured' => $measuredCount,
            'mae' => $this->mean(array_column($measured, 'error'), true),
            'mae_normalized' => $this->mean(array_filter(array_column($measured, 'normalized'), fn ($v) => $v !== null), true),
            'bias' => $this->mean(array_column($measured, 'error'), false),
            'by_block' => $byBlock,
            'by_agent' => $byAgent,
            'flags' => $flags,
            'flag_rejection_rate' => $flagRejectionRate,
            'disagreement_reasons' => $reasons,
            'reliable' => $settledCount >= $minSettled,
        ];
    }

    /**
     * The teacher mark a proposal is measured against, or null when there is
     * nothing to compare it with yet.
     *
     * A rejected proposal on a merely submitted attempt has no mark: the zeros
     * in its block_scores are the auto-grader's placeholders, not teacher marks.
     * Only a graded attempt carries a mark worth measuring against.
     */
    private function finalMark(GradingSuggestion $suggestion): ?float
    {
        if ($suggestion->final_points !== null) {
            return (float) $suggestion->final_points;
        }

        if ($suggestion->state !== GradingSuggestionState::Rejected) {
            return null;
        }

        if ($suggestion->attempt?->status !== AttemptStatus::Graded) {
            return null;
        }

        $scores = $suggestion->attempt->block_scores ?? [];

        if (! is_array($scores) || ! array_key_exists($suggestion->block_id, $scores) || $scores[$suggestion->block_id] === null) {
            return null;
        }

        return (float) $scores[$suggestion->block_id];
    }

    /**
     * @param  array<string, array{settled: int, accepted: int, measured: int, errors: float}>  $table
     */
    private function bump(array &$table, string $key, GradingSuggestion $suggestion, ?float $absError): void
    {
        $table[$key] ??= ['settled' => 0, 'accepted' => 0, 'measured' => 0, 'errors' => 0.0];
        $table[$key]['settled']++;

        if ($suggestion->state === GradingSuggestionState::Accepted) {
            $table[$key]['accepted']++;
        }

        if ($absError !== null) {
            $table[$key]['measured']++;
            $table[$key]['errors'] += $absError;
        }
    }

    /**
     * @param  array<int, float>  $values
     */
    private function mean(array $values, bool $absolute): ?float
    {
        $values = array_values($values);

        if ($values === []) {
            return null;
        }

        if ($absolute) {
            $values = array_map(abs(...), $values);
        }

        return round(array_sum($values) / count($values), $absolute ? 2 : 3);
    }
}
