<?php

namespace App\Console\Commands;

use App\Models\OnlineEvaluation;
use App\Services\Evaluations\MarkdownImporter;
use Illuminate\Console\Command;

class ImportEvaluations extends Command
{
    protected $signature = 'evaluations:import-legacy';

    protected $description = 'Import legacy evaluations for subject 40305';

    public function handle()
    {
        // Limpiar previas de este subject_id para no duplicar
        OnlineEvaluation::where('subject_id', 40305)->delete();

        // 1. evalIA
        $this->info('Importando evalIA (20 preguntas reales)...');

        $pathIA = 'C:\qb\escuela\ispi4013\dontupload\evalIA\EvaluacionIA.md';
        if (file_exists($pathIA)) {
            $markdownIA = file_get_contents($pathIA);
            $parsedIA = MarkdownImporter::parse($markdownIA);
            $parsedIA[0]['title'] = 'Cuestionario Múltiple Choice';
            $parsedIA[0]['intro'] = 'Seleccione la opción correcta para cada pregunta.';
            $contentIA = ['sections' => $parsedIA];
        } else {
            // Fallback en caso de que no exista el archivo
            $contentIA = ['sections' => []];
            $this->error('No se encontró el archivo EvaluacionIA.md');
        }

        OnlineEvaluation::create([
            'subject_id' => 40305,
            'teacher_id' => 1,
            'title' => 'Evaluación de IA',
            'description' => 'Evaluación sobre conceptos básicos de Inteligencia Artificial.',
            'duration_minutes' => 60,
            'status' => 'draft',
            'content' => $contentIA,
        ]);

        // 2. eval3gestion
        $this->info('Importando eval3gestion...');
        $contentGestion = [
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
                            'id' => 'g1_4',
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
                    'blocks' => [
                        [
                            'id' => 'g2_1',
                            'type' => 'table_design',
                            'prompt' => 'Diseñe el modelo de datos para un sistema de Gestión de Ventas.',
                            'points' => 4,
                            'attributes' => [
                                'ID', 'ID', 'ID', 'ID', 'Nombre', 'Teléfono', 'Cargo',
                                'ID_Cliente', 'ID_Empleado', 'Fecha_Venta',
                                'ID_Venta', 'ID_Producto', 'Cantidad_Vendida', 'Precio_Unitario',
                                'Descripcion', 'Stock',
                            ], // Simplificado de 19
                            'tables' => [
                                ['key' => 'clientes', 'name' => 'CLIENTES', 'rows' => 3],
                                ['key' => 'empleados', 'name' => 'EMPLEADOS', 'rows' => 3],
                                ['key' => 'ventas', 'name' => 'VENTAS', 'rows' => 4],
                                ['key' => 'detalle_ventas', 'name' => 'DETALLE_VENTAS', 'rows' => 5],
                                ['key' => 'productos', 'name' => 'PRODUCTOS', 'rows' => 4],
                            ],
                            'relations' => [], // El alumno las crea
                            'relation_types' => ['1:1', '1:N', 'N:N'],
                            'answer_key' => [
                                'tables' => [
                                    ['name' => 'CLIENTES', 'rows' => 3],
                                    ['name' => 'EMPLEADOS', 'rows' => 3],
                                    ['name' => 'VENTAS', 'rows' => 4],
                                    ['name' => 'DETALLE_VENTAS', 'rows' => 5],
                                    ['name' => 'PRODUCTOS', 'rows' => 4],
                                ],
                                'relations' => [
                                    ['from' => 'CLIENTES', 'to' => 'VENTAS'],
                                    ['from' => 'EMPLEADOS', 'to' => 'VENTAS'],
                                    ['from' => 'VENTAS', 'to' => 'DETALLE_VENTAS'],
                                    ['from' => 'PRODUCTOS', 'to' => 'DETALLE_VENTAS'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        OnlineEvaluation::create([
            'subject_id' => 40305,
            'teacher_id' => 1,
            'title' => 'Parcial 3 - Gestión',
            'description' => 'Evaluación de Sistemas de Gestión.',
            'duration_minutes' => 90,
            'status' => 'draft',
            'content' => $contentGestion,
        ]);

        // 3. eval-rec-3SIPGO
        $this->info('Importando eval-rec-3SIPGO...');
        $contentRec = [
            'sections' => [
                [
                    'id' => 'sec_r1',
                    'title' => 'Diseño Relacional - Tema B',
                    'intro' => 'Arme el modelo de datos para Compras.',
                    'blocks' => [
                        [
                            'id' => 'r1_1',
                            'type' => 'table_design',
                            'prompt' => 'Diseñe el modelo de datos (Proveedores, Artículos, Compras, Detalle).',
                            'points' => 10,
                            'attributes' => [
                                'ID', 'ID', 'ID', 'ID', 'Razón_Social', 'Teléfono_Contacto',
                                'Descripción_Artículo', 'Unidad_Medida', 'Stock_Mínimo', 'Categoría',
                                'ID_Proveedor', 'Fecha_Emisión', 'Estado_Orden',
                                'ID_Compra', 'ID_Artículo', 'Cantidad_Pedida', 'Precio_Pactado', 'Subtotal_Línea',
                            ],
                            'tables' => [
                                ['key' => 'proveedores', 'name' => 'PROVEEDORES', 'rows' => 3],
                                ['key' => 'articulos', 'name' => 'ARTÍCULOS', 'rows' => 5],
                                ['key' => 'compras', 'name' => 'COMPRAS', 'rows' => 4],
                                ['key' => 'detalle_compras', 'name' => 'DETALLE_COMPRAS', 'rows' => 6],
                            ],
                            'relations' => [],
                            'relation_types' => ['1:1', '1:N', 'N:N'],
                            'answer_key' => [
                                'tables' => [
                                    ['name' => 'PROVEEDORES', 'rows' => 3],
                                    ['name' => 'ARTÍCULOS', 'rows' => 5],
                                    ['name' => 'COMPRAS', 'rows' => 4],
                                    ['name' => 'DETALLE_COMPRAS', 'rows' => 6],
                                ],
                                'relations' => [
                                    ['from' => 'PROVEEDORES', 'to' => 'COMPRAS'],
                                    ['from' => 'COMPRAS', 'to' => 'DETALLE_COMPRAS'],
                                    ['from' => 'ARTÍCULOS', 'to' => 'DETALLE_COMPRAS'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        OnlineEvaluation::create([
            'subject_id' => 40305,
            'teacher_id' => 1,
            'title' => 'Recuperatorio 3 SIPGO (Tema B)',
            'description' => 'Evaluación de recuperación.',
            'duration_minutes' => 90,
            'status' => 'draft',
            'content' => $contentRec,
        ]);

        $this->info('Evaluaciones importadas exitosamente.');
    }
}
