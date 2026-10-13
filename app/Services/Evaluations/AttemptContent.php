<?php

namespace App\Services\Evaluations;

use Illuminate\Support\Arr;

/**
 * Builds the set of questions a single student is served.
 *
 * A group (section) carrying `draw = N` hands out N of its questions at random;
 * a group without it serves all of them. Every student therefore covers every
 * group while two of them almost never get the same form, which is the point:
 * a shared paper between neighbours is worth nothing as an exam.
 *
 * The returned payload is what gets stored in content_snapshot, and that makes
 * it the single source of truth for what the student was shown. Grading
 * normalises the /10 against this snapshot instead of the authored content, and
 * the assisted grading file exports the questions found here rather than
 * assuming the cohort shared one list.
 *
 * Reordering is deliberately not handled here: shuffling the display order is a
 * separate, opt-in concern (online_evaluations.shuffle_questions) and mixing it
 * in would make the drawn form impossible to reproduce for the teacher.
 */
final class AttemptContent
{
    /**
     * How many questions a group serves at random.
     */
    public static function drawFor(array $section): int
    {
        return max(0, (int) ($section['draw'] ?? 0));
    }

    /**
     * The questions one student receives. Order inside a group follows the
     * author's order, so the two concerns stay separable.
     */
    public static function draw(array $content): array
    {
        $content = EvaluationContent::normalize($content);
        $sections = [];

        foreach ($content['sections'] as $section) {
            $blocks = $section['blocks'] ?? [];
            $draw = self::drawFor($section);

            if ($draw >= 1 && count($blocks) > $draw) {
                $blocks = array_values(Arr::random($blocks, $draw));
            }

            // A group that drew nothing would render as a heading with nothing
            // under it.
            if ($blocks === []) {
                continue;
            }

            $section['blocks'] = $blocks;
            $sections[] = $section;
        }

        $content['sections'] = $sections;

        return $content;
    }

    /**
     * Whether any group hands out alternatives, so callers can stay quiet for
     * the (common) evaluation that serves everything.
     */
    public static function hasDrawnGroups(array $content): bool
    {
        return self::variantGroups($content) !== [];
    }

    /**
     * Groups that hand out alternatives, with every question each one can serve.
     *
     * Groups holding a single question are left out: there is nothing to pick
     * between, so the teacher does not need a selector for them.
     *
     * @return array<int, array{title: string, blocks: array<int, array<string, mixed>>}>
     */
    public static function variantGroups(array $content): array
    {
        $groups = [];

        foreach (EvaluationContent::sections($content) as $index => $section) {
            $blocks = $section['blocks'] ?? [];

            if (self::drawFor($section) < 1 || count($blocks) < 2) {
                continue;
            }

            $groups[] = [
                'title' => (string) ($section['title'] ?? 'Grupo '.($index + 1)),
                'blocks' => $blocks,
            ];
        }

        return $groups;
    }

    /**
     * Cheapest and dearest totals the drawn form can be worth.
     *
     * The /10 normalisation runs against the questions the student actually
     * received, so a group whose alternatives are worth different amounts makes
     * two students' marks incomparable. Surfacing the range is how the editor
     * gets the teacher to keep a group even before the first student sees it.
     *
     * @return array{min: float, max: float}
     */
    public static function scoreRange(array $content): array
    {
        $min = 0.0;
        $max = 0.0;

        foreach (EvaluationContent::sections($content) as $section) {
            $points = array_map(
                fn (array $block) => (float) ($block['points'] ?? 0),
                $section['blocks'] ?? [],
            );

            if ($points === []) {
                continue;
            }

            $draw = self::drawFor($section);

            if ($draw >= 1 && count($points) > $draw) {
                sort($points);
                $min += (float) array_sum(array_slice($points, 0, $draw));
                $max += (float) array_sum(array_slice($points, -$draw));

                continue;
            }

            $total = (float) array_sum($points);
            $min += $total;
            $max += $total;
        }

        return ['min' => round($min, 2), 'max' => round($max, 2)];
    }

    /**
     * Whether a drawn group can change the total a student is marked on.
     */
    public static function variesInPoints(array $content): bool
    {
        return self::scoreRange($content)['min'] !== self::scoreRange($content)['max'];
    }

    /**
     * Total a drawn form carries, as a single number when the groups are even
     * and null when it moves with the draw.
     */
    public static function fixedScore(array $content): ?float
    {
        $range = self::scoreRange($content);

        return $range['min'] === $range['max'] ? $range['min'] : null;
    }
}
