<?php

namespace App\Console\Commands;

use App\Enums\EvaluationStatus;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Seeds the example "Sistemas de Gestión" evaluations for a given subject.
 *
 * The schema answer keys are fully specified (columns, primary key, foreign
 * keys and relation cardinality) so the table_design blocks can be graded
 * automatically. Instances are matched by subject + title, so running this
 * twice updates the same evaluations instead of duplicating them.
 */
class SeedEvaluationExamples extends Command
{
    protected $signature = 'evaluations:seed-examples
        {subject : Subject id to attach the evaluations to}
        {--teacher= : User id of the owning teacher (defaults to the subject teacher)}
        {--replace : Delete the subject existing evaluations first}';

    protected $description = 'Seed the example online evaluations for a subject';

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

        if ($this->option('replace')) {
            $deleted = OnlineEvaluation::where('subject_id', $subject->id)
                ->with('attempts')
                ->get();

            $withAttempts = $deleted->filter(fn ($e) => $e->attempts()->exists());

            if ($withAttempts->isNotEmpty()) {
                $this->error('Hay evaluaciones con intentos registrados. Corré sin --replace para no perder respuestas.');

                return self::FAILURE;
            }

            OnlineEvaluation::where('subject_id', $subject->id)->delete();
            $this->warn("Se eliminaron {$deleted->count()} evaluaciones previas de la materia.");
        }

        foreach ($this->definitions() as $definition) {
            $evaluation = OnlineEvaluation::updateOrCreate(
                ['subject_id' => $subject->id, 'title' => $definition['title']],
                [
                    'teacher_id' => $teacherId,
                    'description' => $definition['description'],
                    'duration_minutes' => $definition['duration_minutes'],
                    'status' => EvaluationStatus::Draft->value,
                    'content' => $definition['content'],
                ]
            );

            $this->info("#{$evaluation->id}  {$evaluation->title}");
        }

        $this->newLine();
        $this->info('Listo. Las evaluaciones quedaron en estado borrador.');

        return self::SUCCESS;
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
                'duration_minutes' => 90,
                'content' => [
                    'sections' => [
                        [
                            'id' => 'sec_g1',
                            'title' => 'Parte 1: Análisis y Diagnóstico Teórico',
                            'intro' => 'Responda las siguientes preguntas de desarrollo.',
                            'blocks' => [
                                [
                                    'id' => 'g1_1',
                                    'type' => 'open_text',
                                    'prompt' => 'Explique la transformación del Dato en Información y brinde un ejemplo.',
                                    'points' => 1.5,
                                ],
                                [
                                    'id' => 'g1_2',
                                    'type' => 'open_text',
                                    'prompt' => 'Elija UNA de las siguientes consignas y desarrolle:',
                                    'points' => 1.5,
                                    'variants' => [
                                        ['key' => 'A', 'title' => 'Caso práctico', 'prompt' => 'Desarrolle un caso práctico de nivel operativo.'],
                                        ['key' => 'B', 'title' => 'Conceptual', 'prompt' => 'Compare Nivel Operativo vs Gerencial.'],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'id' => 'sec_g2',
                            'title' => 'Parte 2: Diseño Relacional',
                            'intro' => 'Diseñe la base de datos según los atributos dados.',
                            'blocks' => [$this->gestionSchemaBlock()],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Recuperatorio 3 SIPGO (Tema B)',
                'description' => 'Evaluación de recuperación.',
                'duration_minutes' => 90,
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
            prompt: 'Diseñe el modelo de datos para un sistema de Gestión de Ventas.',
            points: 8,
            schema: [
                'CLIENTES' => ['columns' => ['ID', 'Nombre', 'Teléfono'], 'pk' => 'ID', 'fks' => []],
                'EMPLEADOS' => ['columns' => ['ID', 'Nombre', 'Teléfono', 'Cargo'], 'pk' => 'ID', 'fks' => []],
                'VENTAS' => [
                    'columns' => ['ID', 'Fecha_Venta', 'ID_Cliente', 'ID_Empleado'],
                    'pk' => 'ID',
                    'fks' => ['ID_Cliente', 'ID_Empleado'],
                ],
                'DETALLE_VENTAS' => [
                    'columns' => ['ID_Venta', 'ID_Producto', 'Cantidad_Vendida', 'Precio_Unitario'],
                    'pk' => null,
                    'fks' => ['ID_Venta', 'ID_Producto'],
                ],
                'PRODUCTOS' => ['columns' => ['ID', 'Descripcion', 'Stock'], 'pk' => 'ID', 'fks' => []],
            ],
            relations: [
                ['from' => 'CLIENTES', 'to' => 'VENTAS', 'type' => '1:N'],
                ['from' => 'EMPLEADOS', 'to' => 'VENTAS', 'type' => '1:N'],
                ['from' => 'VENTAS', 'to' => 'DETALLE_VENTAS', 'type' => '1:N'],
                ['from' => 'PRODUCTOS', 'to' => 'DETALLE_VENTAS', 'type' => '1:N'],
            ],
            rows: ['CLIENTES' => 3, 'EMPLEADOS' => 3, 'VENTAS' => 4, 'DETALLE_VENTAS' => 5, 'PRODUCTOS' => 4]
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
            'intro' => 'Arme el modelo de datos para Compras.',
            'blocks' => [
                $this->schemaBlock(
                    id: 'r1_1',
                    prompt: 'Diseñe el modelo de datos (Proveedores, Artículos, Compras, Detalle).',
                    points: 10,
                    schema: [
                        'PROVEEDORES' => ['columns' => ['ID', 'Razón_Social', 'Teléfono_Contacto'], 'pk' => 'ID', 'fks' => []],
                        'ARTÍCULOS' => [
                            'columns' => ['ID', 'Descripción_Artículo', 'Unidad_Medida', 'Stock_Mínimo', 'Categoría'],
                            'pk' => 'ID',
                            'fks' => [],
                        ],
                        'COMPRAS' => [
                            'columns' => ['ID', 'Fecha_Emisión', 'ID_Proveedor', 'Estado_Orden'],
                            'pk' => 'ID',
                            'fks' => ['ID_Proveedor'],
                        ],
                        'DETALLE_COMPRAS' => [
                            'columns' => ['ID_Compra', 'ID_Artículo', 'Cantidad_Pedida', 'Precio_Pactado', 'Subtotal_Línea'],
                            'pk' => null,
                            'fks' => ['ID_Compra', 'ID_Artículo'],
                        ],
                    ],
                    relations: [
                        ['from' => 'PROVEEDORES', 'to' => 'COMPRAS', 'type' => '1:N'],
                        ['from' => 'COMPRAS', 'to' => 'DETALLE_COMPRAS', 'type' => '1:N'],
                        ['from' => 'ARTÍCULOS', 'to' => 'DETALLE_COMPRAS', 'type' => '1:N'],
                    ],
                    rows: ['PROVEEDORES' => 3, 'ARTÍCULOS' => 5, 'COMPRAS' => 4, 'DETALLE_COMPRAS' => 6]
                ),
            ],
        ]];
    }

    /**
     * Builds a table_design block with a fully specified answer key.
     *
     * `attributes` is the pool the student assigns columns from; it is
     * deduplicated here because the available-attribute badges would otherwise
     * show the same name once per table that uses it.
     *
     * @param  array<string, array{columns: array<int, string>, pk: ?string, fks: array<int, string>}>  $schema
     * @param  array<int, array{from: string, to: string, type: string}>  $relations
     * @param  array<string, int>  $rows
     * @return array<string, mixed>
     */
    private function schemaBlock(
        string $id,
        string $prompt,
        int $points,
        array $schema,
        array $relations,
        array $rows,
    ): array {
        $pool = [];

        foreach ($schema as $definition) {
            foreach ($definition['columns'] as $column) {
                $pool[$column] = true;
            }
        }

        $attributes = array_keys($pool);
        sort($attributes);

        $tables = [];
        $answerTables = [];

        foreach ($schema as $name => $definition) {
            $rowCount = $rows[$name] ?? count($definition['columns']);

            $tables[] = ['key' => Str::slug($name, '_'), 'name' => $name, 'rows' => $rowCount];

            $answerTables[] = [
                'name' => $name,
                'rows' => $rowCount,
                'columns' => $definition['columns'],
                'pk' => $definition['pk'],
                'fks' => $definition['fks'],
            ];
        }

        return [
            'id' => $id,
            'type' => 'table_design',
            'prompt' => $prompt,
            'points' => $points,
            'attributes' => $attributes,
            'tables' => $tables,
            'relations' => [],
            'relation_types' => ['1:1', '1:N', 'N:N'],
            'answer_key' => ['tables' => $answerTables, 'relations' => $relations],
        ];
    }
}
