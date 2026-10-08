<?php

namespace App\Services\Evaluations;

/**
 * A criterion-by-criterion marking scheme for one open_text block.
 *
 * The rubric lives in the block content so a teacher sees the same criteria the
 * assistant grader was asked to apply, and it is snapshotted per attempt for the
 * same reason the content is: editing the rubric afterwards must not reinterpret
 * marks that were proposed against an older version.
 *
 * `max_points` is deliberately not stored. It is derived from the criteria so it
 * cannot drift out of sync with them.
 */
final class Rubric
{
    public const SOURCE_AGENT = 'agent';

    public const SOURCE_TEACHER = 'teacher';

    /**
     * @param  array<int, array{key: string, label: string, points: float, levels: array<string, string>}>  $criteria
     */
    private function __construct(
        public readonly array $criteria,
        public readonly int $version,
        public readonly string $source,
    ) {}

    /**
     * Read the rubric attached to a block, if it has a usable one.
     *
     * @param  array<string, mixed>  $block
     */
    public static function fromBlock(array $block): ?self
    {
        if (empty($block['rubric']) || ! is_array($block['rubric'])) {
            return null;
        }

        $rubric = self::make($block['rubric']);

        return $rubric->criteria === [] ? null : $rubric;
    }

    /**
     * Normalize a raw rubric payload, discarding entries that cannot be marked.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function make(array $raw): self
    {
        $criteria = [];

        foreach (self::normalizeCriteria($raw['criteria'] ?? []) as $index => $criterion) {
            $criteria[] = $criterion + ['levels' => [], 'key' => 'c'.($index + 1)];
        }

        return new self(
            criteria: $criteria,
            version: max(1, (int) ($raw['version'] ?? 1)),
            source: ($raw['source'] ?? self::SOURCE_TEACHER) === self::SOURCE_AGENT
                ? self::SOURCE_AGENT
                : self::SOURCE_TEACHER,
        );
    }

    /**
     * The rubrics of every block that carries one, keyed by block id.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, array{version: int, source: string, criteria: array<int, array{key: string, label: string, points: float, levels: array<string, string>}>}>
     */
    public static function snapshotFor(array $content): array
    {
        $snapshot = [];

        foreach ($content['sections'] ?? [] as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                if (empty($block['id'])) {
                    continue;
                }

                $rubric = self::fromBlock($block);

                if ($rubric !== null) {
                    $snapshot[$block['id']] = $rubric->toArray();
                }
            }
        }

        return $snapshot;
    }

    public function maxPoints(): float
    {
        return round((float) array_sum(array_column($this->criteria, 'points')), 2);
    }

    /**
     * Stable identity of the criteria, so a suggestion can prove which rubric it
     * was produced against.
     */
    public function hash(): string
    {
        return sha1(json_encode($this->criteria));
    }

    /**
     * Reasons this rubric cannot be applied as written, empty when it is sound.
     *
     * @return array<int, string>
     */
    public function problems(float $blockPoints): array
    {
        $problems = [];

        if ($this->criteria === []) {
            $problems[] = 'La rúbrica no tiene criterios.';

            return $problems;
        }

        if (abs($this->maxPoints() - $blockPoints) > 0.001) {
            $problems[] = sprintf(
                'Los criterios suman %.2f y la pregunta vale %.2f.',
                $this->maxPoints(),
                $blockPoints,
            );
        }

        return $problems;
    }

    /**
     * @return array{version: int, source: string, criteria: array<int, array{key: string, label: string, points: float, levels: array<string, string>}>}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'source' => $this->source,
            'criteria' => $this->criteria,
        ];
    }

    /**
     * @return array<int, array{key?: string, label: string, points: float, levels?: array<string, string>}>
     */
    private static function normalizeCriteria(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $criteria = [];
        $seen = [];

        foreach (array_values($raw) as $index => $criterion) {
            if (! is_array($criterion) || blank($criterion['label'] ?? null)) {
                continue;
            }

            $points = round((float) ($criterion['points'] ?? 0), 2);

            if ($points < 0) {
                continue;
            }

            $key = trim((string) ($criterion['key'] ?? ''));

            if ($key === '' || isset($seen[$key])) {
                $key = 'c'.($index + 1);
            }

            while (isset($seen[$key])) {
                $key .= '_';
            }

            $seen[$key] = true;

            $levels = [];

            foreach (($criterion['levels'] ?? []) as $score => $description) {
                if (is_array($description) || $description === null) {
                    continue;
                }

                $levels[(string) $score] = trim((string) $description);
            }

            $criteria[] = [
                'key' => $key,
                'label' => trim((string) $criterion['label']),
                'points' => $points,
                'levels' => $levels,
            ];
        }

        return $criteria;
    }
}
