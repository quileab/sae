<?php

namespace Tests\Feature\Livewire\Evaluations;

use App\Models\Career;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The editor's matching and table_design sub-editors, plus the unsaved guard.
 */
class EvaluationEditorBlocksTest extends TestCase
{
    use RefreshDatabase;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $career = Career::factory()->create(['id' => 11]);
        $this->subject = Subject::create(['id' => 1101, 'career_id' => $career->id, 'name' => 'Base de Datos']);

        session(['current_subject_id' => $this->subject->id]);
    }

    #[Test]
    public function matching_pairs_can_be_added_and_removed(): void
    {
        $component = $this->editor()
            ->call('addBlock', 0, 'matching')
            ->call('addPair', 0, 0)
            ->call('addPair', 0, 0);

        $pairs = $component->get('sections')[0]['blocks'][0]['pairs'];

        $this->assertCount(3, $pairs, 'addBlock seeds one pair, then two are added.');

        $component->call('removePair', 0, 0, 0);

        $this->assertCount(2, $component->get('sections')[0]['blocks'][0]['pairs']);
    }

    #[Test]
    public function a_new_matching_block_starts_with_one_editable_pair(): void
    {
        $component = $this->editor()->call('addBlock', 0, 'matching');

        $block = $component->get('sections')[0]['blocks'][0];

        $this->assertCount(1, $block['pairs']);
        $this->assertSame(['prompt' => 'Término 1', 'answer' => 'Concepto 1'], $block['pairs'][0]);
    }

    #[Test]
    public function schema_attributes_can_be_added_and_removed(): void
    {
        $component = $this->editor()
            ->call('addBlock', 0, 'table_design')
            ->call('addAttribute', 0, 0)
            ->set('sections.0.blocks.0.attributes.0', 'ID');

        $this->assertSame(['ID'], $component->get('sections')[0]['blocks'][0]['attributes']);

        $component->call('removeAttribute', 0, 0, 0);

        $this->assertSame([], $component->get('sections')[0]['blocks'][0]['attributes']);
    }

    #[Test]
    public function tables_can_be_added_and_removed(): void
    {
        $component = $this->editor()
            ->call('addBlock', 0, 'table_design')
            ->call('addSchemaTable', 0, 0);

        $tables = $component->get('sections')[0]['blocks'][0]['tables'];

        $this->assertCount(1, $tables);
        $this->assertSame('', $tables[0]['name']);

        $component->call('removeSchemaTable', 0, 0, 0);

        $this->assertSame([], $component->get('sections')[0]['blocks'][0]['tables']);
    }

    #[Test]
    public function removing_a_table_also_drops_its_answer_key_entry_and_relations(): void
    {
        $component = $this->editor()->set('sections', $this->schemaSection());

        $component->call('removeSchemaTable', 0, 0, 0);

        $this->assertCount(1, $component->get('sections')[0]['blocks'][0]['tables']);

        $component->call('removeSchemaTable', 0, 0, 0);

        $block = $component->get('sections')[0]['blocks'][0];

        $this->assertSame([], $block['tables']);
        $this->assertSame([], $block['answer_key']['tables'], 'The key entry must go with the table.');
        $this->assertSame(
            [],
            $block['answer_key']['relations'],
            'Relations referencing the removed table must be dropped too.'
        );
    }

    #[Test]
    public function a_primary_key_can_be_set_and_cleared(): void
    {
        $component = $this->editor()->set('sections', $this->schemaSection(pk: null, fks: ['ID_Cliente']));

        $component->call('setKeyPrimaryKey', 0, 0, 0, 'Fecha');

        $table = $component->get('sections')[0]['blocks'][0]['answer_key']['tables'][0];
        $this->assertSame('Fecha', $table['pk']);

        // Clicking the same column again clears the primary key.
        $component->call('setKeyPrimaryKey', 0, 0, 0, 'Fecha');

        $this->assertNull($component->get('sections')[0]['blocks'][0]['answer_key']['tables'][0]['pk']);
    }

    #[Test]
    public function a_primary_key_replaces_a_foreign_key_on_the_same_column(): void
    {
        // The fixture declares ID both as primary key and as a foreign key,
        // which is contradictory; setting the key must resolve it.
        $component = $this->editor()->set('sections', $this->schemaSection());

        $component->call('setKeyPrimaryKey', 0, 0, 0, 'Fecha');

        $table = $component->get('sections')[0]['blocks'][0]['answer_key']['tables'][0];

        $this->assertSame('Fecha', $table['pk']);
        $this->assertNotContains('Fecha', $table['fks']);
    }

    #[Test]
    public function foreign_keys_toggle(): void
    {
        $component = $this->editor()->set('sections', $this->schemaSection());

        // 'Fecha' starts out as neither a primary key nor a foreign key.
        $component->call('toggleKeyForeignKey', 0, 0, 0, 'Fecha');

        $this->assertContains('Fecha', $component->get('sections')[0]['blocks'][0]['answer_key']['tables'][0]['fks']);

        $component->call('toggleKeyForeignKey', 0, 0, 0, 'Fecha');

        $this->assertNotContains('Fecha', $component->get('sections')[0]['blocks'][0]['answer_key']['tables'][0]['fks']);
    }

    #[Test]
    public function removing_a_column_clears_it_as_primary_key_and_foreign_key(): void
    {
        $component = $this->editor()->set('sections', $this->schemaSection());

        $component->call('removeKeyColumn', 0, 0, 0, 0); // removes 'ID', the pk and an fk

        $table = $component->get('sections')[0]['blocks'][0]['answer_key']['tables'][0];

        $this->assertNull($table['pk']);
        $this->assertNotContains('ID', $table['fks']);
        $this->assertNotContains('ID', $table['columns']);
    }

    #[Test]
    public function relation_cardinality_cycles_back_to_the_default(): void
    {
        $component = $this->editor()->set('sections', $this->schemaSection());

        $component->call('toggleKeyRelationType', 0, 0, 0, 'N:N');

        $this->assertSame(
            'N:N',
            $component->get('sections')[0]['blocks'][0]['answer_key']['relations'][0]['type']
        );

        $component->call('toggleKeyRelationType', 0, 0, 0, 'N:N');

        $this->assertSame(
            '1:N',
            $component->get('sections')[0]['blocks'][0]['answer_key']['relations'][0]['type'],
            'Toggling the active cardinality resets it to the default.'
        );
    }

    #[Test]
    public function the_editor_reports_the_available_points(): void
    {
        $component = $this->editor()
            ->call('addBlock', 0, 'multiple_choice')
            ->set('sections.0.blocks.0.points', 4)
            ->call('addBlock', 0, 'open_text')
            ->set('sections.0.blocks.1.points', 6);

        $this->assertSame(10.0, $component->instance()->totalPoints());
        $this->assertSame(2, $component->instance()->questionCount());
    }

    #[Test]
    public function editing_marks_the_form_dirty_and_saving_clears_it(): void
    {
        $component = $this->editor()->assertSet('isDirty', false);

        $component->set('title', 'Nuevo título')->assertSet('isDirty', true);

        $component->set('title', 'Parcial')
            ->set('passing_score', 7)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isDirty', false);
    }

    private function editor()
    {
        return Volt::actingAs(User::factory()->create(['role' => 'teacher']))
            ->test('evaluations.editor');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function schemaSection(?string $pk = 'ID', array $fks = ['ID', 'ID_Cliente']): array
    {
        return [[
            'id' => 'sec_1',
            'title' => 'Diseño',
            'intro' => '',
            'blocks' => [[
                'id' => 'blk_1',
                'type' => 'table_design',
                'prompt' => 'Diseñe el modelo',
                'points' => 10,
                'attributes' => ['ID', 'ID_Cliente', 'Fecha'],
                'tables' => [
                    ['key' => 'ventas', 'name' => 'VENTAS', 'rows' => 2],
                    ['key' => 'clientes', 'name' => 'CLIENTES', 'rows' => 2],
                ],
                'relations' => [],
                'relation_types' => ['1:1', '1:N', 'N:N'],
                'answer_key' => [
                    'tables' => [
                        [
                            'name' => 'VENTAS',
                            'columns' => ['ID', 'ID_Cliente', 'Fecha'],
                            'pk' => $pk,
                            'fks' => $fks,
                        ],
                        ['name' => 'CLIENTES', 'columns' => ['ID'], 'pk' => 'ID', 'fks' => []],
                    ],
                    'relations' => [['from' => 'CLIENTES', 'to' => 'VENTAS', 'type' => '1:N']],
                ],
            ]],
        ]];
    }
}
