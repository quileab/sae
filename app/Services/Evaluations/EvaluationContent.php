<?php

namespace App\Services\Evaluations;

class EvaluationContent
{
    /**
     * Calcula el puntaje máximo total de la evaluación sumando los puntos de cada bloque.
     */
    public static function getMaxScore(array $content): float
    {
        $maxScore = 0;
        $sections = $content['sections'] ?? [];
        foreach ($sections as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                $maxScore += (float) ($block['points'] ?? 0);
            }
        }

        return $maxScore;
    }

    /**
     * Normaliza el contenido JSON para asegurar que tiene secciones y bloques.
     */
    public static function normalize(array $content): array
    {
        // Si el contenido viejo era un array directo de bloques (opción múltiple)
        if (! isset($content['sections'])) {
            $blocks = [];
            foreach ($content as $block) {
                $blocks[] = self::normalizeBlock($block);
            }

            return [
                'sections' => [
                    [
                        'title' => 'Evaluación',
                        'intro' => '',
                        'blocks' => $blocks,
                    ],
                ],
            ];
        }

        $normalizedSections = [];
        foreach ($content['sections'] as $section) {
            $normalizedBlocks = [];
            foreach ($section['blocks'] ?? [] as $block) {
                $normalizedBlocks[] = self::normalizeBlock($block);
            }
            $section['blocks'] = $normalizedBlocks;
            $normalizedSections[] = $section;
        }

        $content['sections'] = $normalizedSections;

        return $content;
    }

    protected static function normalizeBlock(array $block): array
    {
        if (! isset($block['id'])) {
            $block['id'] = uniqid('blk_');
        }
        if (! isset($block['points'])) {
            $block['points'] = 1;
        }

        if (! isset($block['type'])) {
            $block['type'] = 'open_text';
        }

        switch ($block['type']) {
            case 'multiple_choice':
                if (! isset($block['options']) || ! is_array($block['options'])) {
                    $block['options'] = [];
                }
                if (! isset($block['correct_option'])) {
                    $block['correct_option'] = null;
                }
                break;
            case 'true_false':
                if (! isset($block['correct_option'])) {
                    $block['correct_option'] = 'true';
                }
                break;
            case 'checkboxes':
                if (! isset($block['options']) || ! is_array($block['options'])) {
                    $block['options'] = [];
                }
                if (! isset($block['correct_options']) || ! is_array($block['correct_options'])) {
                    $block['correct_options'] = [];
                }
                break;
            case 'matching':
                if (! isset($block['pairs']) || ! is_array($block['pairs'])) {
                    $block['pairs'] = [];
                }
                break;
            case 'table_design':
                if (! isset($block['attributes']) || ! is_array($block['attributes'])) {
                    $block['attributes'] = [];
                }
                if (! isset($block['tables']) || ! is_array($block['tables'])) {
                    $block['tables'] = [];
                }
                if (! isset($block['relations']) || ! is_array($block['relations'])) {
                    $block['relations'] = [];
                }
                if (! isset($block['relation_types']) || ! is_array($block['relation_types'])) {
                    $block['relation_types'] = ['1:1', '1:N', 'N:N'];
                }
                break;
        }

        return $block;
    }

    /**
     * Evalúa una respuesta contra un bloque de contenido.
     * Devuelve el puntaje obtenido y si requiere corrección manual.
     *
     * @return array{score: float, requires_manual: bool}
     */
    public static function gradeBlock(array $block, mixed $answer): array
    {
        $points = (float) ($block['points'] ?? 1);

        if (empty($answer) && $answer !== '0' && $answer !== 0 && $answer !== false && ! is_array($answer)) {
            return ['score' => 0, 'requires_manual' => false];
        }

        switch ($block['type']) {
            case 'multiple_choice':
            case 'true_false':
                $correct = ($block['correct_option'] ?? null) === $answer;

                return [
                    'score' => $correct ? $points : 0,
                    'requires_manual' => false,
                ];

            case 'checkboxes':
                if (! is_array($answer)) {
                    return ['score' => 0, 'requires_manual' => false];
                }
                $expected = $block['correct_options'] ?? [];

                // Moodle-like fractional grading could be complex, let's do all-or-nothing or simple fractional
                // Simple partial grading: point per correct choice, minus point per wrong choice (clamped to 0)
                $correctChoices = 0;
                $wrongChoices = 0;
                foreach ($answer as $selected) {
                    if (in_array($selected, $expected)) {
                        $correctChoices++;
                    } else {
                        $wrongChoices++;
                    }
                }

                $totalExpected = count($expected);
                if ($totalExpected === 0) {
                    return ['score' => $points, 'requires_manual' => false];
                }

                $fraction = ($correctChoices - $wrongChoices) / $totalExpected;
                $earned = max(0, $fraction * $points);

                return [
                    'score' => $earned,
                    'requires_manual' => false,
                ];

            case 'matching':
                if (! is_array($answer)) {
                    return ['score' => 0, 'requires_manual' => false];
                }
                $expectedPairs = $block['pairs'] ?? [];
                $totalPairs = count($expectedPairs);
                if ($totalPairs === 0) {
                    return ['score' => $points, 'requires_manual' => false];
                }

                $correct = 0;
                foreach ($expectedPairs as $index => $pair) {
                    $expectedMatch = $pair['match'] ?? $pair['answer'] ?? '';
                    if (isset($answer[$index]) && $answer[$index] === $expectedMatch) {
                        $correct++;
                    }
                }

                return [
                    'score' => ($correct / $totalPairs) * $points,
                    'requires_manual' => false,
                ];

            case 'table_design':
                return self::gradeTableDesign($block, $answer, $points);

            case 'short_text':
                if (! empty($block['correct_answer'])) {
                    $isCorrect = mb_strtolower(trim($answer)) === mb_strtolower(trim($block['correct_answer']));

                    return [
                        'score' => $isCorrect ? $points : 0,
                        'requires_manual' => false,
                    ];
                }

                return ['score' => 0, 'requires_manual' => true];

            case 'open_text':
            default:
                return ['score' => 0, 'requires_manual' => true];
        }
    }

    protected static function gradeTableDesign(array $block, mixed $answer, float $maxPoints): array
    {
        if (! is_array($answer)) {
            return ['score' => 0, 'requires_manual' => false];
        }

        $expectedTables = $block['answer_key']['tables'] ?? [];
        $expectedRelations = $block['answer_key']['relations'] ?? [];

        $totalExpectedItems = count($expectedTables) + count($expectedRelations);
        if ($totalExpectedItems === 0) {
            // Si no hay answer_key, requiere corrección manual
            return ['score' => 0, 'requires_manual' => true];
        }

        $pointsPerItem = $maxPoints / $totalExpectedItems;
        $earnedPoints = 0;

        $studentTables = $answer['tables'] ?? [];
        foreach ($expectedTables as $expectedTable) {
            foreach ($studentTables as $studentTable) {
                if (mb_strtolower(trim($studentTable['name'])) === mb_strtolower(trim($expectedTable['name']))) {
                    $earnedPoints += $pointsPerItem;
                    break;
                }
            }
        }

        $studentRelations = $answer['relations'] ?? [];
        foreach ($expectedRelations as $expectedRelation) {
            foreach ($studentRelations as $studentRelation) {
                if (
                    mb_strtolower(trim($studentRelation['from'])) === mb_strtolower(trim($expectedRelation['from'])) &&
                    mb_strtolower(trim($studentRelation['to'])) === mb_strtolower(trim($expectedRelation['to']))
                ) {
                    $earnedPoints += $pointsPerItem;
                    break;
                }
            }
        }

        return [
            'score' => min($maxPoints, $earnedPoints),
            'requires_manual' => false,
        ];
    }
}
