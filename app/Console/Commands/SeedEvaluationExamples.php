<?php

namespace App\Console\Commands;

use App\Enums\EvaluationStatus;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\MarkdownImporter;
use Illuminate\Console\Command;

/**
 * Seeds the example online evaluations for a subject.
 *
 * The relational-design answer keys below are transcribed from the original
 * source material and cross-checked against real student submissions:
 *
 *   eval3gestion      js/eval.js  ce={clientes:3,empleados:3,ventas:4,detalle_ventas:5,productos:4}
 *                     entregas-eval3gestion/BUYATTI_MARIASOL-*.json (nota 10)
 *   eval-rec-3SIPGO   js/eval.js  ce={proveedores:3,articulos:5,compras:4,detalle_compras:6}
 *                     entregas/BENITEZ_MARTINA-*.json
 *
 * Two details of the source are easy to get wrong and are preserved here:
 *
 *  - The attribute pool intentionally repeats tokens (5x "ID" in the sales
 *    exercise, 4x "ID" in the purchases one, plus repeated "Nombre" and
 *    "Precio_Unitario"). Every table needs its own primary key named exactly
 *    "ID", so the pool must keep one token per table. Deduplicating it makes
 *    the exercise impossible to complete.
 *  - The purchases .md file is stale: it lists ID_Detalle_Compra and repeats
 *    ID_Proveedor/ID_Compra/ID_Articulo, while the shipped js and the real
 *    submissions use generic "ID" throughout. This follows the js.
 */
class SeedEvaluationExamples extends Command
{
    protected $signature = 'evaluations:seed-examples
        {subject : Subject id to attach the evaluations to}
        {--teacher= : User id of the owning teacher (defaults to the subject teacher)}
        {--markdown-dir= : Folder holding the source evaluations; imports evalIA/EvaluacionIA.md when present}
        {--replace : Delete the subject existing evaluations first}';

    protected $description = 'Seed the example online evaluations for a subject';

    /**
     * Minutes configured in every source config.json.
     */
    private const DURATION_MINUTES = 100;

    public function handle(): int
    {
        $subject = Subject::find((int) $this->argument('subject'));

        if ($subject === null) {
            $this->error('La materia indicada no existe.');

            return self::FAILURE;
        }

        $teacherId = $this->resolveTeacher($subject);

        if ($teacherId === null) {
            $this->error('No se pudo determinar el docente. Pasá --teacher con un id de usuario.');

            return self::FAILURE;
        }

        if ($this->option('replace') && ! $this->replaceExisting($subject)) {
            return self::FAILURE;
        }

        foreach ($this->definitions() as $definition) {
            $this->seed($definition, $subject->id, $teacherId);
        }

        $this->seedMarkdownEvaluation($subject->id, $teacherId);

        $this->newLine();
        $this->info('Listo. Las evaluaciones quedaron en estado borrador.');

        return self::SUCCESS;
    }

    private function replaceExisting(Subject $subject): bool
    {
        $existing = OnlineEvaluation::where('subject_id', $subject->id)->get();

        if ($existing->contains(fn (OnlineEvaluation $e) => $e->attempts()->exists())) {
            $this->error('Hay evaluaciones con intentos registrados. Corré sin --replace para no perder respuestas.');

            return false;
        }

        OnlineEvaluation::where('subject_id', $subject->id)->delete();

        $this->warn("Se eliminaron {$existing->count()} evaluaciones previas de la materia.");

        return true;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seed(array $definition, int $subjectId, int $teacherId): OnlineEvaluation
    {
        $evaluation = OnlineEvaluation::updateOrCreate(
            ['subject_id' => $subjectId, 'title' => $definition['title']],
            [
                'teacher_id' => $teacherId,
                'description' => $definition['description'],
                'duration_minutes' => $definition['duration_minutes'],
                'status' => EvaluationStatus::Draft->value,
                'content' => $definition['content'],
            ]
        );

        $this->info("#{$evaluation->id}  {$evaluation->title}");

        return $evaluation;
    }

    /**
     * Imports the multiple-choice evaluation from its Markdown source when a
     * source folder is provided. The file is never hardcoded: the folder comes
     * from --markdown-dir so the command stays reproducible elsewhere.
     */
    private function seedMarkdownEvaluation(int $subjectId, int $teacherId): void
    {
        $directory = $this->option('markdown-dir');

        if (! $directory) {
            $this->line('Sin --markdown-dir: no se importa la evaluación de opción múltiple.');

            return;
        }

        $path = rtrim((string) $directory, '/\\').'/evalIA/EvaluacionIA.md';

        if (! is_readable($path)) {
            $this->warn("No se encontró {$path}: se omite la evaluación de opción múltiple.");

            return;
        }

        $sections = MarkdownImporter::parse((string) file_get_contents($path));
        $sections[0]['title'] = 'Cuestionario de Opción Múltiple';
        $sections[0]['intro'] = 'Seleccione la opción correcta de cada pregunta.';

        $this->seed([
            'title' => 'Evaluación de IA',
            'description' => 'Evaluación sobre conceptos de Inteligencia Artificial.',
            'duration_minutes' => self::DURATION_MINUTES,
            'content' => ['sections' => $sections],
        ], $subjectId, $teacherId);
    }

    private function resolveTeacher(Subject $subject): ?int
    {
        $teacherId = $this->option('teacher');

        if ($teacherId && User::whereKey($teacherId)->exists()) {
            return (int) $teacherId;
        }

        return $subject->classSessions()
            ->whereNotNull('teacher_id')
            ->value('teacher_id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'title' => 'Parcial 3 - Gestión',
                'description' => 'Evaluación de Sistemas de Gestión.',
                'duration_minutes' => self::DURATION_MINUTES,
                'content' => ['sections' => [
                    [
                        'id' => 'sec_g1',
                        'title' => 'Parte 1: Análisis y Diagnóstico Teórico',
                        'intro' => 'Respondé las cuatro preguntas de desarrollo.',
                        'blocks' => [
                            [
                                'id' => 'g1_1',
                                'type' => 'open_text',
                                'prompt' => 'Explique la diferencia entre un dato en bruto y la información útil para la alta gerencia, y brinde un ejemplo de cómo un volumen de transacciones diarias puede procesarse para apoyar la toma de decisiones en Ventas y Marketing.',
                                'points' => 1.5,
                            ],
                            [
                                'id' => 'g1_2',
                                'type' => 'open_text',
                                'prompt' => '¿Qué consecuencias puede tener para una organización la presencia de datos redundantes, imprecisos o no normalizados en sus bases operacionales? Describa un escenario donde un error en un atributo afecte la toma de decisiones gerenciales.',
                                'points' => 1.5,
                            ],
                            [
                                'id' => 'g1_3',
                                'type' => 'open_text',
                                'prompt' => '¿Por qué es fundamental la integridad referencial basada en PK y FK al vincular múltiples tablas? ¿Qué problema ocurriría si se registrara un detalle de venta asociado a un cliente o producto que no existe en las tablas maestras?',
                                'points' => 1.5,
                            ],
                            [
                                'id' => 'g1_4',
                                'type' => 'open_text',
                                'prompt' => 'Diferencie una consulta puntual operativa de un reporte gerencial consolidado, y explique por qué el procesamiento de datos acumulados es indispensable para la dirección.',
                                'points' => 1.5,
                                'variants' => [
                                    [
                                        'key' => 'caso',
                                        'title' => 'Orientada a casos reales (Caso práctico)',
                                        'prompt' => 'Análisis de tendencias para la toma de decisiones: si el gerente debe decidir en qué región abrir una nueva sucursal, ¿por qué no le alcanza con mirar los tickets de venta individuales? Explique cómo el agrupamiento de datos transforma las transacciones en información útil.',
                                    ],
                                    [
                                        'key' => 'conceptual',
                                        'title' => 'Opción directa y conceptual (Nivel Operativo vs. Nivel Gerencial)',
                                        'prompt' => 'Diferencie la información que necesita un empleado operativo para registrar una venta de la que necesita un administrador para evaluar el desempeño global. ¿Cómo ayudan las tablas dinámicas a pasar de una necesidad a la otra?',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'sec_g2',
                        'title' => 'Parte 2: Diseño Relacional',
                        'intro' => 'Distribuí los atributos en las tablas, marcá las claves PK/FK e indicá las relaciones.',
                        'blocks' => [$this->gestionSchemaBlock()],
                    ],
                ]],
            ],
            [
                'title' => 'Recuperatorio 3 SIPGO (Tema B)',
                'description' => 'Evaluación de recuperación.',
                'duration_minutes' => self::DURATION_MINUTES,
                'content' => ['sections' => $this->recuperatorioSections()],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function gestionSchemaBlock(): array
    {
        return $this->schemaBlock(
            id: 'g2_1',
            prompt: 'Diseñe el modelo de datos de un Sistema de Gestión e Histórico de Ventas.',
            points: 10,
            // Pool exactly as shipped: five "ID" (one primary key per table),
            // two "Nombre", two "Precio_Unitario".
            attributes: [
                'ID', 'ID', 'ID', 'ID', 'ID',
                'ID_Cliente', 'ID_Empleado', 'ID_Producto', 'ID_Venta',
                'Nombre', 'Nombre',
                'Cargo', 'Descripcion', 'Fecha_Venta',
                'Cantidad_Vendida', 'Precio_Unitario', 'Precio_Unitario',
                'Stock', 'Teléfono',
            ],
            tables: [
                'clientes' => 3,
                'empleados' => 3,
                'ventas' => 4,
                'detalle_ventas' => 5,
                'productos' => 4,
            ],
            schema: [
                'clientes' => ['columns' => ['ID', 'Nombre', 'Teléfono'], 'pk' => 'ID', 'fks' => []],
                'empleados' => ['columns' => ['ID', 'Nombre', 'Cargo'], 'pk' => 'ID', 'fks' => []],
                'ventas' => [
                    'columns' => ['ID', 'Fecha_Venta', 'ID_Cliente', 'ID_Empleado'],
                    'pk' => 'ID',
                    'fks' => ['ID_Cliente', 'ID_Empleado'],
                ],
                'detalle_ventas' => [
                    'columns' => ['ID', 'ID_Venta', 'ID_Producto', 'Cantidad_Vendida', 'Precio_Unitario'],
                    'pk' => 'ID',
                    'fks' => ['ID_Venta', 'ID_Producto'],
                ],
                'productos' => ['columns' => ['ID', 'Descripcion', 'Precio_Unitario', 'Stock'], 'pk' => 'ID', 'fks' => []],
            ],
            // Vertical order of the diagram: two sources side by side, then the
            // table they feed, then the detail, then the remaining source.
            layout: [['clientes', 'empleados'], ['ventas'], ['detalle_ventas'], ['productos']],
            relationPairs: [
                ['clave' => 'cliente_ventas', 'from' => 'clientes', 'to' => 'ventas'],
                ['clave' => 'empleado_ventas', 'from' => 'empleados', 'to' => 'ventas'],
                ['clave' => 'ventas_detalle', 'from' => 'ventas', 'to' => 'detalle_ventas'],
                ['clave' => 'producto_detalle', 'from' => 'productos', 'to' => 'detalle_ventas'],
            ],
            relationTypes: [
                ['clave' => 'cliente_ventas', 'type' => '1:N'],
                ['clave' => 'empleado_ventas', 'type' => '1:N'],
                ['clave' => 'ventas_detalle', 'type' => '1:N'],
                ['clave' => 'producto_detalle', 'type' => '1:N'],
            ],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recuperatorioSections(): array
    {
        return [[
            'id' => 'sec_r1',
            'title' => 'Diseño Relacional - Tema B',
            'intro' => 'Arme el modelo de datos del Sistema de Gestión de Compras y Abastecimiento.',
            'blocks' => [
                $this->schemaBlock(
                    id: 'r1_1',
                    prompt: 'Diseñe el modelo de datos (Proveedores, Artículos, Compras, Detalle de Compras).',
                    points: 10,
                    // Pool exactly as shipped: four "ID" (one primary key per table).
                    attributes: [
                        'ID', 'ID', 'ID', 'ID',
                        'ID_Artículo', 'ID_Compra', 'ID_Proveedor',
                        'Cantidad_Pedida', 'Categoría', 'Descripción_Artículo', 'Estado_Orden',
                        'Fecha_Emisión', 'Precio_Pactado', 'Razón_Social', 'Stock_Mínimo',
                        'Subtotal_Línea', 'Teléfono_Contacto', 'Unidad_Medida',
                    ],
                    tables: [
                        'proveedores' => 3,
                        'articulos' => 5,
                        'compras' => 4,
                        'detalle_compras' => 6,
                    ],
                    schema: [
                        'proveedores' => [
                            'columns' => ['ID', 'Razón_Social', 'Teléfono_Contacto'],
                            'pk' => 'ID',
                            'fks' => [],
                        ],
                        'articulos' => [
                            'columns' => ['ID', 'Descripción_Artículo', 'Categoría', 'Stock_Mínimo', 'Unidad_Medida'],
                            'pk' => 'ID',
                            'fks' => [],
                        ],
                        'compras' => [
                            'columns' => ['ID', 'Fecha_Emisión', 'Estado_Orden', 'ID_Proveedor'],
                            'pk' => 'ID',
                            'fks' => ['ID_Proveedor'],
                        ],
                        'detalle_compras' => [
                            'columns' => ['ID', 'ID_Compra', 'ID_Artículo', 'Cantidad_Pedida', 'Precio_Pactado', 'Subtotal_Línea'],
                            'pk' => 'ID',
                            'fks' => ['ID_Compra', 'ID_Artículo'],
                        ],
                    ],
                    layout: [['proveedores', 'articulos'], ['compras'], ['detalle_compras']],
                    relationPairs: [
                        ['clave' => 'proveedor_compras', 'from' => 'proveedores', 'to' => 'compras'],
                        ['clave' => 'compras_detalle', 'from' => 'compras', 'to' => 'detalle_compras'],
                        ['clave' => 'articulo_detalle', 'from' => 'articulos', 'to' => 'detalle_compras'],
                    ],
                    relationTypes: [
                        ['clave' => 'proveedor_compras', 'type' => '1:N'],
                        ['clave' => 'compras_detalle', 'type' => '1:N'],
                        ['clave' => 'articulo_detalle', 'type' => '1:N'],
                    ],
                ),
            ],
        ]];
    }

    /**
     * Builds a table_design block.
     *
     * The attribute pool is passed through untouched: repeated tokens are part
     * of the exercise, not duplicated data.
     *
     * @param  array<int, string>  $attributes
     * @param  array<string, int>  $tables  key => number of slots
     * @param  array<string, array{columns: array<int, string>, pk: ?string, fks: array<int, string>}>  $schema
     * @param  array<int, array{clave: string, from: string, to: string}>  $relationPairs
     * @param  array<int, array{clave: string, type: string}>  $relationTypes
     * @param  array<int, array<int, string>>  $layout  vertical bands, in drawing order
     * @return array<string, mixed>
     */
    private function schemaBlock(
        string $id,
        string $prompt,
        int $points,
        array $attributes,
        array $tables,
        array $schema,
        array $relationPairs,
        array $relationTypes,
        array $layout,
    ): array {
        $tableRows = [];
        $answerTables = [];

        foreach ($tables as $key => $slots) {
            $tableRows[] = ['key' => $key, 'name' => $key, 'rows' => $slots];

            $answerTables[] = [
                'name' => $key,
                'rows' => $slots,
                'columns' => $schema[$key]['columns'],
                'pk' => $schema[$key]['pk'],
                'fks' => $schema[$key]['fks'],
            ];
        }

        return [
            'id' => $id,
            'type' => 'table_design',
            'prompt' => $prompt,
            'points' => $points,
            'attributes' => array_values($attributes),
            'tables' => $tableRows,
            'layout' => array_values($layout),
            'relation_pairs' => $relationPairs,
            'relation_types' => ['1:1', '1:N', 'N:N'],
            'answer_key' => [
                'tables' => $answerTables,
                'relations' => $relationTypes,
            ],
        ];
    }
}
