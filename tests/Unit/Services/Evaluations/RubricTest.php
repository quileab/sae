<?php

namespace Tests\Unit\Services\Evaluations;

use App\Services\Evaluations\EvaluationContent;
use App\Services\Evaluations\Rubric;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Rubrics are the contract handed to an assistant grader, so the invariants that
 * keep a marking round coherent live here: they must be markable, they must add
 * up to the question they belong to, and they must be frozen per attempt.
 */
class RubricTest extends TestCase
{
    #[Test]
    public function it_derives_the_maximum_from_the_criteria_instead_of_storing_it(): void
    {
        $rubric = Rubric::make(['criteria' => [
            ['label' => 'Define el concepto', 'points' => 0.5],
            ['label' => 'Lo aplica al caso', 'points' => 1.0],
        ]]);

        $this->assertSame(1.5, $rubric->maxPoints());
        $this->assertArrayNotHasKey('max_points', $rubric->toArray());
    }

    #[Test]
    public function it_defaults_to_the_teacher_and_keeps_the_agent_when_declared(): void
    {
        $criteria = [['label' => 'Criterio', 'points' => 1.0]];

        $this->assertSame(Rubric::SOURCE_TEACHER, Rubric::make(['criteria' => $criteria])->source);
        $this->assertSame(Rubric::SOURCE_AGENT, Rubric::make(['source' => 'agent', 'criteria' => $criteria])->source);
        $this->assertSame(Rubric::SOURCE_TEACHER, Rubric::make(['source' => 'inventado', 'criteria' => $criteria])->source);
    }

    #[Test]
    public function it_drops_criteria_that_cannot_be_marked(): void
    {
        $rubric = Rubric::make(['criteria' => [
            ['label' => 'Válido', 'points' => 1.5],
            ['points' => 1.0],
            ['label' => '  ', 'points' => 1.0],
            ['label' => 'Negativo', 'points' => -3],
            'no es un array',
        ]]);

        $this->assertCount(1, $rubric->criteria);
        $this->assertSame('Válido', $rubric->criteria[0]['label']);
    }

    #[Test]
    public function it_gives_every_criterion_a_unique_key_and_keeps_the_declared_one(): void
    {
        $rubric = Rubric::make(['criteria' => [
            ['key' => 'dato', 'label' => 'Uno', 'points' => 0.5],
            ['key' => 'dato', 'label' => 'Duplicado', 'points' => 0.5],
            ['label' => 'Sin clave', 'points' => 0.5],
        ]]);

        $keys = array_column($rubric->criteria, 'key');

        $this->assertSame($keys, array_unique($keys));
        $this->assertSame('dato', $keys[0]);
        $this->assertSame(1.5, $rubric->maxPoints(), 'Renaming keys must not change the total.');
    }

    #[Test]
    public function it_drops_garbage_levels_and_rounds_the_points(): void
    {
        $rubric = Rubric::make(['criteria' => [[
            'label' => 'Criterio',
            'points' => 0.333,
            'levels' => ['0' => '  nada  ', '0.5' => ['anidado'], '1' => null, '2' => 5],
        ]]]);

        $criterion = $rubric->criteria[0];

        $this->assertSame(0.33, $criterion['points']);
        $this->assertSame(['0' => 'nada', '2' => '5'], $criterion['levels']);
    }

    #[Test]
    public function it_reports_when_the_criteria_do_not_add_up_to_the_question(): void
    {
        $rubric = Rubric::make(['criteria' => [['label' => 'Único', 'points' => 1.0]]]);

        $this->assertSame([], $rubric->problems(1.0), 'Criteria that add up produce no complaint.');
        $this->assertSame(
            ['Los criterios suman 1.00 y la pregunta vale 1.50.'],
            $rubric->problems(1.5),
        );
    }

    #[Test]
    public function a_block_without_a_usable_rubric_has_none(): void
    {
        $this->assertNull(Rubric::fromBlock(['id' => 'q1', 'type' => 'open_text']));
        $this->assertNull(Rubric::fromBlock(['id' => 'q1', 'type' => 'open_text', 'rubric' => ['criteria' => []]]));
        $this->assertNull(Rubric::fromBlock(['id' => 'q1', 'type' => 'open_text', 'rubric' => 'basura']));
    }

    #[Test]
    public function the_hash_changes_only_when_the_criteria_change(): void
    {
        $criteria = [['key' => 'c1', 'label' => 'A', 'points' => 1.5]];

        $first = Rubric::make(['criteria' => $criteria]);
        $same = Rubric::make(['version' => 7, 'source' => 'agent', 'criteria' => $criteria]);
        $other = Rubric::make(['criteria' => [['key' => 'c1', 'label' => 'B', 'points' => 1.5]]]);

        $this->assertSame($first->hash(), $same->hash(), 'Version and source are metadata, not the criteria.');
        $this->assertNotSame($first->hash(), $other->hash());
    }

    #[Test]
    public function it_snapshots_only_the_blocks_that_carry_a_rubric(): void
    {
        $content = ['sections' => [
            ['blocks' => [
                ['id' => 'q1', 'type' => 'open_text', 'points' => 1.5, 'rubric' => ['criteria' => [['label' => 'A', 'points' => 1.5]]]],
                ['id' => 'q2', 'type' => 'open_text', 'points' => 1.5],
            ]],
            ['blocks' => [
                ['id' => 'q3', 'type' => 'open_text', 'points' => 1.5, 'rubric' => ['criteria' => [['label' => 'B', 'points' => 1.5]]]],
                ['id' => '', 'type' => 'open_text', 'rubric' => ['criteria' => [['label' => 'huérfana', 'points' => 1]]]],
            ]],
        ]];

        $snapshot = Rubric::snapshotFor($content);

        $this->assertSame(['q1', 'q3'], array_keys($snapshot));
        $this->assertSame('A', $snapshot['q1']['criteria'][0]['label']);
        $this->assertSame(Rubric::SOURCE_TEACHER, $snapshot['q1']['source']);
    }

    #[Test]
    public function an_empty_content_snapshot_yields_an_empty_snapshot(): void
    {
        $this->assertSame([], Rubric::snapshotFor([]));
        $this->assertSame([], Rubric::snapshotFor(['sections' => []]));
    }

    #[Test]
    public function normalising_a_block_keeps_a_sound_rubric_and_drops_a_broken_one(): void
    {
        $sound = EvaluationContent::normalize(['sections' => [['blocks' => [[
            'id' => 'q1',
            'type' => 'open_text',
            'points' => 1.5,
            'rubric' => ['source' => 'agent', 'criteria' => [['label' => 'A', 'points' => 1.5]]],
        ]]]]]);

        $broken = EvaluationContent::normalize(['sections' => [['blocks' => [[
            'id' => 'q2',
            'type' => 'open_text',
            'points' => 1.5,
            'rubric' => ['criteria' => [['label' => '', 'points' => 1.5]]],
        ]]]]]);

        $this->assertSame('agent', $sound['sections'][0]['blocks'][0]['rubric']['source']);
        $this->assertNull($broken['sections'][0]['blocks'][0]['rubric']);
    }
}
