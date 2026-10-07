<?php

namespace Tests\Feature\Evaluations;

use App\Models\Career;
use App\Models\ClassSession;
use App\Models\OnlineEvaluation;
use App\Models\Subject;
use App\Models\User;
use App\Services\Evaluations\ErDiagramLayout;
use App\Services\Evaluations\EvaluationContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The seeded examples must stay faithful to the source material.
 *
 * Shapes and answer keys are transcribed from the original exercises and
 * cross-checked against real submissions:
 *   eval3gestion/js/eval.js           ce={clientes:3,empleados:3,ventas:4,detalle_ventas:5,productos:4}
 *   eval-rec-3SIPGO/js/eval.js        ce={proveedores:3,articulos:5,compras:4,detalle_compras:6}
 *   entregas-eval3gestion/BUYATTI_*   nota 10
 *   entregas/BENITEZ_MARTINA-*        SIPGO, correct
 */
class EvaluationSeedFidelityTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 40305, 'career_id' => $career->id, 'name' => 'SIPGO']);

        ClassSession::create([
            'subject_id' => $this->subject->id,
            'date' => now()->toDateString(),
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'class_number' => 1,
            'unit' => 'U1',
            'type' => 'regular',
            'content' => '',
        ]);
    }

    #[Test]
    public function it_seeds_the_relational_examples(): void
    {
        $this->seedExamples();

        $this->assertEqualsCanonicalizing(
            ['Parcial 3 - Gestión', 'Recuperatorio 3 SIPGO (Tema B)'],
            OnlineEvaluation::pluck('title')->all()
        );
    }

    #[Test]
    public function it_imports_the_multiple_choice_example_from_a_markdown_folder(): void
    {
        $directory = $this->fakeSourceFolder();

        $this->artisan('evaluations:seed-examples', [
            'subject' => $this->subject->id,
            '--markdown-dir' => $directory,
        ])->assertSuccessful();

        $blocks = $this->blocks(OnlineEvaluation::where('title', 'Evaluación de IA')->firstOrFail());

        $this->assertCount(2, $blocks);
        $this->assertSame('multiple_choice', $blocks[0]['type']);
        $this->assertSame('B', $blocks[0]['correct_option']);
        $this->assertSame(['A', 'B', 'C'], array_column($blocks[0]['options'], 'key'));
    }

    #[Test]
    public function a_missing_markdown_folder_is_skipped_without_failing(): void
    {
        $this->artisan('evaluations:seed-examples', [
            'subject' => $this->subject->id,
            '--markdown-dir' => $this->tempPath('no-existe'),
        ])->assertSuccessful();

        $this->assertSame(2, OnlineEvaluation::count());
    }

    #[Test]
    public function every_example_keeps_the_configured_duration_and_stays_a_draft(): void
    {
        $this->seedExamples();

        foreach (OnlineEvaluation::all() as $evaluation) {
            $this->assertSame(100, $evaluation->duration_minutes, $evaluation->title);
            $this->assertSame('draft', $evaluation->status, $evaluation->title);
        }
    }

    #[Test]
    public function the_attribute_pool_has_exactly_one_token_per_slot(): void
    {
        $this->seedExamples();

        foreach ($this->schemaBlocks() as [$title, $block]) {
            $slots = collect($block['tables'])->sum('rows');

            $this->assertSame(
                $slots,
                count($block['attributes']),
                "{$title}: every attribute must fit into a slot, duplicates included."
            );
        }
    }

    #[Test]
    public function the_pool_repeats_the_generic_primary_key_once_per_table(): void
    {
        $this->seedExamples();

        foreach ($this->schemaBlocks() as [$title, $block]) {
            $tables = count($block['tables']);

            $this->assertSame(
                $tables,
                collect($block['attributes'])->filter(fn ($a) => $a === 'ID')->count(),
                "{$title}: each table needs its own generic ID primary key."
            );
        }
    }

    #[Test]
    public function the_sales_exercise_matches_the_source_shape(): void
    {
        $this->seedExamples();

        [, $block] = $this->schemaBlock('Parcial 3 - Gestión');

        $this->assertSame(
            ['clientes' => 3, 'empleados' => 3, 'ventas' => 4, 'detalle_ventas' => 5, 'productos' => 4],
            collect($block['tables'])->mapWithKeys(fn ($t) => [$t['name'] => $t['rows']])->all(),
            'js/eval.js: ce={clientes:3,empleados:3,ventas:4,detalle_ventas:5,productos:4}'
        );

        $this->assertCount(4, $block['relation_pairs']);
    }

    #[Test]
    public function the_purchases_exercise_matches_the_source_shape(): void
    {
        $this->seedExamples();

        [, $block] = $this->schemaBlock('Recuperatorio 3 SIPGO (Tema B)');

        $this->assertSame(
            ['proveedores' => 3, 'articulos' => 5, 'compras' => 4, 'detalle_compras' => 6],
            collect($block['tables'])->mapWithKeys(fn ($t) => [$t['name'] => $t['rows']])->all(),
            'js/eval.js: ce={proveedores:3,articulos:5,compras:4,detalle_compras:6}'
        );

        $this->assertCount(3, $block['relation_pairs']);
    }

    #[Test]
    public function every_table_answer_key_fits_its_own_slots(): void
    {
        $this->seedExamples();

        foreach ($this->schemaBlocks() as [$title, $block]) {
            $slots = collect($block['tables'])->mapWithKeys(fn ($t) => [$t['name'] => $t['rows']]);

            foreach ($block['answer_key']['tables'] as $key) {
                $this->assertLessThanOrEqual(
                    $slots[$key['name']],
                    count($key['columns']),
                    "{$title}/{$key['name']}: the expected columns must fit in the available cells."
                );
            }
        }
    }

    #[Test]
    public function the_detail_tables_keep_their_generic_primary_key(): void
    {
        $this->seedExamples();

        [, $gestion] = $this->schemaBlock('Parcial 3 - Gestión');
        [, $sipgo] = $this->schemaBlock('Recuperatorio 3 SIPGO (Tema B)');

        foreach ([[$gestion, 'detalle_ventas'], [$sipgo, 'detalle_compras']] as [$block, $name]) {
            $key = collect($block['answer_key']['tables'])->firstWhere('name', $name);

            $this->assertSame('ID', $key['pk'], "{$name} must keep a generic ID primary key.");
            $this->assertContains('ID', $key['columns']);
        }
    }

    #[Test]
    public function a_perfect_submission_scores_full_marks_on_both_exercises(): void
    {
        $this->seedExamples();

        foreach ($this->schemaBlocks() as [$title, $block]) {
            $answer = $this->perfectAnswerFor($block);

            $result = EvaluationContent::gradeTableDesignDetailed($block, $answer, (float) $block['points']);

            $this->assertSame(
                (float) $block['points'],
                $result['score'],
                "{$title}: a fully correct design must earn every point."
            );
            $this->assertFalse($result['requires_manual']);
        }
    }

    #[Test]
    public function every_declared_connector_is_gradeable(): void
    {
        $this->seedExamples();

        foreach ($this->schemaBlocks() as [$title, $block]) {
            $pairs = array_column($block['relation_pairs'], 'clave');
            $keyed = array_column($block['answer_key']['relations'], 'clave');

            $this->assertSame($pairs, $keyed, "{$title}: every connector needs an expected cardinality.");
            $this->assertSame($pairs, array_unique($pairs), "{$title}: connector keys must be unique.");
        }
    }

    #[Test]
    public function the_declared_layout_matches_the_source_diagrams(): void
    {
        $this->seedExamples();

        [, $gestion] = $this->schemaBlock('Parcial 3 - Gestión');
        [, $sipgo] = $this->schemaBlock('Recuperatorio 3 SIPGO (Tema B)');

        $gestionBands = array_map(
            fn (array $band) => $band['tables'],
            ErDiagramLayout::bands($gestion['tables'], $gestion['relation_pairs'], $gestion['layout'])
        );
        $sipgoBands = array_map(
            fn (array $band) => $band['tables'],
            ErDiagramLayout::bands($sipgo['tables'], $sipgo['relation_pairs'], $sipgo['layout'])
        );

        $this->assertSame([
            ['clientes', 'empleados'],
            ['ventas'],
            ['detalle_ventas'],
            ['productos'],
        ], $gestionBands);

        $this->assertSame([
            ['proveedores', 'articulos'],
            ['compras'],
            ['detalle_compras'],
        ], $sipgoBands);
    }

    #[Test]
    public function the_ia_example_imports_all_twenty_questions(): void
    {
        $source = $this->realSourceFolder();

        if (! is_readable($source.'/evalIA/EvaluacionIA.md')) {
            $this->markTestSkipped('Las evaluaciones fuente no están disponibles en esta máquina.');
        }

        $this->artisan('evaluations:seed-examples', [
            'subject' => $this->subject->id,
            '--markdown-dir' => $source,
        ])->assertSuccessful();

        $evaluation = OnlineEvaluation::where('title', 'Evaluación de IA')->firstOrFail();
        $blocks = $this->blocks($evaluation);

        $this->assertCount(20, $blocks);

        foreach ($blocks as $block) {
            $this->assertSame('multiple_choice', $block['type']);
            $this->assertCount(4, $block['options'], $block['prompt']);
            $this->assertContains($block['correct_option'], ['A', 'B', 'C', 'D']);
        }
    }

    #[Test]
    public function the_theoretical_questions_keep_their_alternatives(): void
    {
        $this->seedExamples();

        $blocks = $this->blocks(OnlineEvaluation::where('title', 'Parcial 3 - Gestión')->firstOrFail());

        $this->assertCount(5, $blocks, 'Four written questions plus the schema exercise.');
        $this->assertCount(4, collect($blocks)->where('type', 'open_text'));

        $withVariants = collect($blocks)->first(fn (array $b) => ($b['type'] ?? null) === 'open_text' && ! empty($b['variants']));

        $this->assertNotNull($withVariants, 'Question 4 offers two alternatives.');
        $this->assertSame(['caso', 'conceptual'], array_column($withVariants['variants'], 'key'));
    }

    #[Test]
    public function seeding_twice_does_not_duplicate_the_examples(): void
    {
        $this->seedExamples();
        $this->seedExamples();

        $this->assertSame(2, OnlineEvaluation::count());
    }

    private function fakeSourceFolder(): string
    {
        $directory = $this->tempPath('sources');
        $folder = $directory.'/evalIA';

        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        file_put_contents($folder.'/EvaluacionIA.md', implode('
', [
            '# **Evaluación de la capacitación**',
            '',
            '### **1\. Primera pregunta de prueba?**',
            '',
            'A. Opción A',
            ' B. Opción B',
            ' C. Opción C',
            '',
            '**Respuesta correcta: B**',
            '',
            'Explicación de por qué B es la correcta.',
            '',
            '---',
            '',
            '### **2\. Segunda pregunta?**',
            '',
            'A. Opción A',
            ' B. Opción B',
            ' C. Opción C',
            '',
            '**Respuesta correcta: A**',
        ]));

        return $directory;
    }

    private function tempPath(string $name): string
    {
        return sys_get_temp_dir().'/sae-evaluations-test/'.$name;
    }

    /**
     * The original exercises, when this machine has them checked out.
     */
    private function realSourceFolder(): string
    {
        return env('SAE_EVALUATION_SOURCES', 'C:/qb/escuela/ispi4013/dontupload');
    }

    private function seedExamples(): void
    {
        $this->artisan('evaluations:seed-examples', [
            'subject' => $this->subject->id,
        ])->assertSuccessful();
    }

    /**
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    private function schemaBlocks(): array
    {
        return [
            $this->schemaBlock('Parcial 3 - Gestión'),
            $this->schemaBlock('Recuperatorio 3 SIPGO (Tema B)'),
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function schemaBlock(string $title): array
    {
        $evaluation = OnlineEvaluation::where('title', $title)->firstOrFail();

        return [$title, collect($this->blocks($evaluation))->firstWhere('type', 'table_design')];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function blocks(OnlineEvaluation $evaluation): array
    {
        return collect($evaluation->content['sections'] ?? [])
            ->flatMap(fn (array $section) => $section['blocks'] ?? [])
            ->values()
            ->all();
    }

    /**
     * Builds the submission the answer key describes.
     *
     * @param  array<string, mixed>  $block
     * @return array{tables: array<int, array<string, mixed>>, relations: array<int, array{clave: string, type: string}>}
     */
    private function perfectAnswerFor(array $block): array
    {
        $tables = array_map(function (array $key) {
            return [
                'name' => $key['name'],
                'columns' => array_map(function (string $column) use ($key) {
                    $type = match (true) {
                        ($key['pk'] ?? null) === $column => 'PK',
                        in_array($column, $key['fks'] ?? [], true) => 'FK',
                        default => '',
                    };

                    return ['type' => $type, 'name' => $column];
                }, $key['columns']),
            ];
        }, $block['answer_key']['tables']);

        return [
            'tables' => $tables,
            'relations' => array_map(
                fn (array $relation) => ['clave' => $relation['clave'], 'type' => $relation['type']],
                $block['answer_key']['relations']
            ),
        ];
    }
}
