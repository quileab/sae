<?php

use App\Livewire\ClassSessions\Students;
use App\Models\Career;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentAcademicStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->student = User::factory()->create(['role' => 'student']);
    $this->career = Career::factory()->create();
    $this->subject = Subject::create([
        'id' => rand(1000, 9999),
        'name' => 'Test Subject',
        'career_id' => $this->career->id,
    ]);

    // Enroll student and admin
    $this->student->careers()->attach($this->career);
    Enrollment::create([
        'user_id' => $this->student->id,
        'subject_id' => $this->subject->id,
        'status' => 'active',
    ]);

    Enrollment::create([
        'user_id' => $this->admin->id,
        'subject_id' => $this->subject->id,
        'status' => 'active',
    ]);

    $this->session = ClassSession::create([
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->admin->id,
        'date' => date('Y-m-d'),
        'unit' => 'Unit 1',
        'content' => 'Test Content',
        'class_number' => 1,
        'type' => 'Presencial',
    ]);
});

test('can save evaluation note type even when grade and attendance are zero', function () {
    Livewire::withQueryParams(['subject_id' => $this->subject->id])
        ->actingAs($this->admin)
        ->test(Students::class, [
            'id' => $this->session->id,
        ])
        ->call('attendance', $this->student->id)
        ->set('grades.type', 'evaluation')
        ->set('grades.attendance', 0)
        ->set('grades.grade', 0)
        ->set('grades.approved', 0)
        ->set('grades.comments', '')
        ->call('saveGrade')
        ->assertHasNoErrors();

    // Verify that the record actually exists in the database and has the correct type
    $this->assertDatabaseHas('grades', [
        'user_id' => $this->student->id,
        'class_session_id' => $this->session->id,
        'type' => 'evaluation',
    ]);
});

test('recuperatory replaces the linked evaluation in the effective EV average', function () {
    $evSession = ClassSession::create([
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->admin->id,
        'date' => date('Y-m-d'),
        'unit' => 'Unit 1',
        'content' => 'EV Content',
        'class_number' => 1,
        'type' => 'Presencial',
    ]);

    $recSession = ClassSession::create([
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->admin->id,
        'date' => date('Y-m-d'),
        'unit' => 'Unit 2',
        'content' => 'REC Content',
        'class_number' => 2,
        'type' => 'Presencial',
    ]);

    $evaluation = Grade::create([
        'user_id' => $this->student->id,
        'class_session_id' => $evSession->id,
        'type' => 'evaluation',
        'grade' => 2,
        'attendance' => 100,
    ]);

    $recovery = Grade::create([
        'user_id' => $this->student->id,
        'class_session_id' => $recSession->id,
        'type' => 'recuperatory',
        'grade' => 9,
        'attendance' => 100,
        'recovered_grade_id' => $evaluation->id,
    ]);

    $service = app(EnrollmentAcademicStatusService::class);
    $effective = $service->resolveEffectiveEvaluations(collect([$evaluation, $recovery]));

    expect($effective)->toHaveCount(1);
    expect($effective->first()->grade)->toBe(9);
    expect($service->resolveEffectiveEvaluations(collect([$evaluation, $recovery]))->avg('grade'))->toBe(9);
});

test('can save a recuperatory note linked to an evaluation', function () {
    $evSession = ClassSession::create([
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->admin->id,
        'date' => date('Y-m-d'),
        'unit' => 'Unit 1',
        'content' => 'EV Content',
        'class_number' => 1,
        'type' => 'Presencial',
    ]);

    $recSession = ClassSession::create([
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->admin->id,
        'date' => date('Y-m-d'),
        'unit' => 'Unit 2',
        'content' => 'REC Content',
        'class_number' => 2,
        'type' => 'Presencial',
    ]);

    Grade::create([
        'user_id' => $this->student->id,
        'class_session_id' => $evSession->id,
        'type' => 'evaluation',
        'grade' => 4,
        'attendance' => 100,
    ]);

    Livewire::withQueryParams(['subject_id' => $this->subject->id])
        ->actingAs($this->admin)
        ->test(Students::class, [
            'id' => $recSession->id,
        ])
        ->call('attendance', $this->student->id)
        ->set('grades.type', 'recuperatory')
        ->set('grades.grade', 8)
        ->set('grades.attendance', 100)
        ->set('grades.recovered_grade_id', Grade::where('class_session_id', $evSession->id)->first()->id)
        ->call('saveGrade')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('grades', [
        'user_id' => $this->student->id,
        'class_session_id' => $recSession->id,
        'type' => 'recuperatory',
        'grade' => 8,
    ]);
});
