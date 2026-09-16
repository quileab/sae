<?php

namespace Tests\Feature\Livewire\Subjects;

use App\Livewire\Subjects\SubjectTable;
use App\Models\Career;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubjectTableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_add_a_subject_with_logical_id(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // 1. Create career 11
        $career = Career::create(['id' => 11, 'name' => 'Carrera 11']);

        // 2. Start Livewire
        $component = Livewire::test(SubjectTable::class)
            ->set('career_id', $career->id);

        // 3. Add first subject
        $component->call('add');

        $subject1 = Subject::where('career_id', 11)->first();
        $this->assertEquals(1101, $subject1->id);

        // 4. Add second subject
        $component->call('add');

        $subject2 = Subject::where('career_id', 11)->where('id', 1102)->first();
        $this->assertNotNull($subject2);
        $this->assertEquals(1102, $subject2->id);
    }
}
