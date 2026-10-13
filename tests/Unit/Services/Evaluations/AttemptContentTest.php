<?php

namespace Tests\Unit\Services\Evaluations;

use App\Services\Evaluations\AttemptContent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A group that sorts alternatives is what makes two papers different, and the
 * mark a student gets is normalised against whatever they were served. These
 * cover both: the draw itself, and the total it can add up to.
 */
class AttemptContentTest extends TestCase
{
    #[Test]
    public function a_group_without_a_draw_serves_every_question(): void
    {
        $served = AttemptContent::draw($this->content([['a'], ['b'], ['c']]));

        $this->assertSame(['a', 'b', 'c'], $this->ids($served));
    }

    #[Test]
    public function a_drawn_group_serves_exactly_the_questions_asked_for(): void
    {
        $served = AttemptContent::draw($this->content([['a'], ['b'], ['c'], ['d']], draw: 2));

        $this->assertCount(2, $this->ids($served));
        $this->assertEmpty(array_diff($this->ids($served), ['a', 'b', 'c', 'd']));
        $this->assertSame($this->ids($served), array_unique($this->ids($served)), 'The same question is never served twice.');
    }

    #[Test]
    public function every_group_is_drawn_independently(): void
    {
        $served = AttemptContent::draw([
            'sections' => [
                ['id' => 's1', 'title' => 'Unidad 1', 'draw' => 1, 'blocks' => [
                    ['id' => 'u1a', 'type' => 'open_text'],
                    ['id' => 'u1b', 'type' => 'open_text'],
                ]],
                ['id' => 's2', 'title' => 'Unidad 2', 'draw' => 1, 'blocks' => [
                    ['id' => 'u2a', 'type' => 'open_text'],
                    ['id' => 'u2b', 'type' => 'open_text'],
                ]],
            ],
        ]);

        // One question per group, never two from the same group and never zero.
        $this->assertCount(1, $served['sections'][0]['blocks']);
        $this->assertCount(1, $served['sections'][1]['blocks']);

        $ids = $this->allIds($served);
        $this->assertCount(2, $ids);
        $this->assertEmpty(array_diff($ids, ['u1a', 'u1b', 'u2a', 'u2b']));
        $this->assertStringStartsWith('u1', $ids[0], 'The first group contributed one question.');
        $this->assertStringStartsWith('u2', $ids[1], 'The second group contributed one question.');
    }

    #[Test]
    public function a_group_keeps_its_title_and_intro_after_being_drawn(): void
    {
        $served = AttemptContent::draw([
            'sections' => [[
                'id' => 's1',
                'title' => 'Unidad 1: Entidades',
                'intro' => 'Respondé una.',
                'draw' => 1,
                'blocks' => [['id' => 'a', 'type' => 'open_text'], ['id' => 'b', 'type' => 'open_text']],
            ]],
        ]);

        $this->assertSame('Unidad 1: Entidades', $served['sections'][0]['title']);
        $this->assertSame('Respondé una.', $served['sections'][0]['intro']);
    }

    #[Test]
    public function asking_for_more_questions_than_the_group_holds_serves_them_all(): void
    {
        $served = AttemptContent::draw($this->content([['a'], ['b']], draw: 5));

        $this->assertSame(['a', 'b'], $this->ids($served));
    }

    #[Test]
    public function an_empty_group_is_left_out_instead_of_rendering_an_empty_heading(): void
    {
        $served = AttemptContent::draw([
            'sections' => [
                ['id' => 's1', 'title' => 'Vacía', 'blocks' => []],
                ['id' => 's2', 'title' => 'Unidad 1', 'draw' => 1, 'blocks' => [['id' => 'a', 'type' => 'open_text'], ['id' => 'b', 'type' => 'open_text']]],
            ],
        ]);

        $this->assertCount(1, $served['sections']);
        $this->assertSame('Unidad 1', $served['sections'][0]['title']);
    }

    #[Test]
    public function a_group_holding_one_question_needs_no_selector(): void
    {
        $content = $this->content([['a']], draw: 1);

        $this->assertFalse(AttemptContent::hasDrawnGroups($content));
        $this->assertSame([], AttemptContent::variantGroups($content));
    }

    #[Test]
    public function a_group_holding_alternatives_is_reported_as_a_variant_group(): void
    {
        $content = $this->content([['a'], ['b']], draw: 1);

        $this->assertTrue(AttemptContent::hasDrawnGroups($content));
        $this->assertCount(1, AttemptContent::variantGroups($content));
        $this->assertSame('Unidad 1', AttemptContent::variantGroups($content)[0]['title']);
    }

    #[Test]
    public function even_alternatives_keep_the_total_the_same_for_everyone(): void
    {
        $content = $this->content([['a', 2.0], ['b', 2.0], ['c', 2.0]], draw: 1);

        $this->assertSame(['min' => 2.0, 'max' => 2.0], AttemptContent::scoreRange($content));
        $this->assertSame(2.0, AttemptContent::fixedScore($content));
        $this->assertFalse(AttemptContent::variesInPoints($content));
    }

    #[Test]
    public function uneven_alternatives_expose_the_range_a_mark_can_be_worth(): void
    {
        $content = $this->content([['a', 1.0], ['b', 3.0]], draw: 1);

        $this->assertSame(['min' => 1.0, 'max' => 3.0], AttemptContent::scoreRange($content));
        $this->assertNull(AttemptContent::fixedScore($content));
        $this->assertTrue(AttemptContent::variesInPoints($content), 'A mark is not comparable across students then.');
    }

    #[Test]
    public function the_range_counts_a_group_of_questions_that_is_not_drawn_in_full(): void
    {
        // Two of three, so the cheapest and dearest draw are the two extremes.
        $content = $this->content([['a', 1.0], ['b', 2.0], ['c', 5.0]], draw: 2);

        $this->assertSame(['min' => 3.0, 'max' => 7.0], AttemptContent::scoreRange($content));
    }

    /**
     * @param  array<int, array{0: string, 1?: float}>  $blocks  id, and optionally points
     * @return array<string, mixed>
     */
    private function content(array $blocks, int $draw = 0): array
    {
        return ['sections' => [[
            'id' => 's1',
            'title' => 'Unidad 1',
            'draw' => $draw,
            'blocks' => array_map(fn (array $block) => [
                'id' => $block[0],
                'type' => 'open_text',
                'points' => $block[1] ?? 1,
            ], $blocks),
        ]]];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, string>
     */
    private function ids(array $content): array
    {
        return array_column($content['sections'][0]['blocks'] ?? [], 'id');
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, string>
     */
    private function allIds(array $content): array
    {
        $ids = [];

        foreach ($content['sections'] ?? [] as $section) {
            foreach ($section['blocks'] ?? [] as $block) {
                $ids[] = $block['id'];
            }
        }

        return $ids;
    }
}
