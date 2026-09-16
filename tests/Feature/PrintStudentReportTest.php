<?php

use App\Models\Career;
use App\Models\ClassSession;
use App\Models\Config;
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

    // Enroll student
    $this->student->careers()->attach($this->career);
    $this->subject->users()->attach($this->student);

    // Create a class session in the first semester
    $this->session = ClassSession::create([
        'subject_id' => $this->subject->id,
        'teacher_id' => $this->admin->id,
        'date' => date('Y').'-04-10',
        'unit' => 'Unit 1',
        'content' => 'Introduction',
        'class_number' => 1,
        'type' => 'Presencial',
    ]);

    // Add grade/attendance
    Grade::create([
        'user_id' => $this->student->id,
        'class_session_id' => $this->session->id,
        'attendance' => 100,
        'grade' => 9,
        'type' => 'evaluation',
        'comments' => 'EV1',
    ]);

    Config::create([
        'id' => 'label_subject',
        'value' => 'Materia',
        'group' => 'system',
        'type' => 'text',
        'description' => 'Label Subject',
    ]);
});

test('admin can generate student report', function () {
    $this->actingAs($this->admin)
        ->get(route('print.student-report', $this->subject->id))
        ->assertOk()
        ->assertViewIs('print.student-report')
        ->assertSee('Advanced Math')
        ->assertSee($this->student->lastname);
});

test('students cannot generate their own report from the print route', function () {
    $this->actingAs($this->student)
        ->get(route('print.student-report', $this->subject->id))
        ->assertForbidden();
});

test('teacher can generate report for their subject', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);

    $this->actingAs($teacher)
        ->get(route('print.student-report', $this->subject->id))
        ->assertOk();
});
