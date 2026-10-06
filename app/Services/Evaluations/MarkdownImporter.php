<?php

namespace App\Services\Evaluations;

class MarkdownImporter
{
    /**
     * Parse markdown content into evaluation blocks.
     * Returns an array of sections with blocks.
     */
    public static function parse(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $blocks = [];
        $current = null;

        foreach ($lines as $line) {
            $line = rtrim($line);

            // Pregunta: ### **1\. Texto** o ### 1. Texto
            if (preg_match('/^#{1,4}\s+(?:\*\*)?(\d+)\\\?.\s*(.+?)(?:\*\*)?\s*$/', $line, $m)) {
                if ($current !== null) {
                    $blocks[] = self::finalizeBlock($current);
                }
                $current = [
                    'id' => 'q_'.$m[1].'_'.uniqid(),
                    'type' => 'open_text', // Default, might change to multiple_choice
                    'prompt' => trim($m[2]),
                    'points' => 1,
                    'options' => [],
                    'correct_option' => null,
                    'explanation' => '',
                    'variants' => [],
                ];

                continue;
            }

            if ($current === null) {
                continue;
            }

            // Opciones: A. Texto
            if (preg_match('/^\s*([A-D])\.\s+(.+)$/', $line, $m)) {
                $current['type'] = 'multiple_choice';
                $current['options'][] = [
                    'key' => $m[1],
                    'text' => trim($m[2]),
                ];

                continue;
            }

            // Respuesta correcta
            if (preg_match('/\*\*Respuesta correcta:\s*([A-D])\*\*/i', $line, $m)) {
                $current['correct_option'] = strtoupper($m[1]);

                continue;
            }

            // Explicación
            if ($current['correct_option'] !== null && $line !== '' && ! preg_match('/^#{1,4}\s/', $line)) {
                if ($current['explanation'] === '') {
                    $current['explanation'] = trim($line);
                } else {
                    $current['explanation'] .= "\n".trim($line);
                }

                continue;
            }

            // Variantes (muy básico, detecta líneas que parecen opciones de desarrollo)
            // Por ejemplo si dice "- Variante A:"
            if (preg_match('/^-\s+Variante\s+([A-Z]):\s*(.+)$/i', $line, $m)) {
                $current['variants'][] = [
                    'key' => $m[1],
                    'title' => 'Variante '.$m[1],
                    'prompt' => trim($m[2]),
                ];

                continue;
            }
        }

        if ($current !== null) {
            $blocks[] = self::finalizeBlock($current);
        }

        return [
            [
                'title' => 'Preguntas Importadas',
                'intro' => '',
                'blocks' => $blocks,
            ],
        ];
    }

    protected static function finalizeBlock(array $raw): array
    {
        $block = [
            'id' => $raw['id'],
            'type' => $raw['type'],
            'prompt' => $raw['prompt'],
            'points' => $raw['points'],
        ];

        if ($raw['type'] === 'multiple_choice') {
            $block['options'] = $raw['options'];
            $block['correct_option'] = $raw['correct_option'];
            $block['explanation'] = $raw['explanation'];
        }

        if (! empty($raw['variants'])) {
            $block['variants'] = $raw['variants'];
        }

        return $block;
    }
}
