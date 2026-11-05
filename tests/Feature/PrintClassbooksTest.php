<?php

use App\Models\Career;
use App\Models\ClassSession;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->student = User::factory()->create(['role' => 'student']);
    $this->career = Career::factory()->create();
    $this->subject = Subject::create([
        'id' => rand(1000, 9999),
        'name' => 'Advanced Math',
        'career_id' => $this->career->id,
    ]);

    $this->student->careers()->attach($this->career);
    $this->subject->users()->attach($this->student);

    $this->session = ClassSession::create([
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->admin->id,
        'date' => date('Y').'-04-10',
        'unit' => 'Unit 1',
        'content' => 'Introduction',
        'class_number' => 1,
        'type' => 'Presencial',
    ]);
});

function createPracticalWorkGrade(array $attributes = []): Grade
{
    $defaults = [
        'user_id' => test()->student->id,
        'class_session_id' => test()->session->id,
        'attendance' => 100,
        'grade' => 0,
        'type' => Grade::TYPE_PRACTICAL_WORK,
        'approved' => 1,
        'comments' => 'TP1',
    ];

    return Grade::create(array_merge($defaults, $attributes));
}

test('practical work approved without numeric grade is shown', function () {
    createPracticalWorkGrade();

    $this->actingAs($this->admin)
        ->get(route('printclassbooks', [$this->subject->id, $this->student->id]))
        ->assertOk()
        ->assertSee('TP Aprobado')
        ->assertSee('TP1');
});

test('practical work not approved is shown without note', function () {
    createPracticalWorkGrade(['approved' => 0]);

    $this->actingAs($this->admin)
        ->get(route('printclassbooks', [$this->subject->id, $this->student->id]))
        ->assertOk()
        ->assertSee('TP Sin aprobar')
        ->assertDontSee('Nota:');
});

test('practical work with numeric grade shows the note', function () {
    createPracticalWorkGrade(['approved' => 1, 'grade' => 8]);

    $this->actingAs($this->admin)
        ->get(route('printclassbooks', [$this->subject->id, $this->student->id]))
        ->assertOk()
        ->assertSee('TP Aprobado')
        ->assertSee('Nota: 8');
});

test('regular grade keeps showing numeric note', function () {
    Grade::create([
        'user_id' => $this->student->id,
        'class_session_id' => $this->session->id,
        'attendance' => 100,
        'grade' => 8,
        'type' => Grade::TYPE_EVALUATION,
        'comments' => 'EV1',
    ]);

    $this->actingAs($this->admin)
        ->get(route('printclassbooks', [$this->subject->id, $this->student->id]))
        ->assertOk()
        ->assertSee('Nota: 8')
        ->assertDontSee('TP Aprobado');
});
