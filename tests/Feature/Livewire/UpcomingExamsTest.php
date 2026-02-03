<?php

use App\Livewire\UpcomingExams;
use App\Models\Career;
use App\Models\Event;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders successfully', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UpcomingExams::class)
        ->assertStatus(200);
});

test('shows upcoming exams for student', function () {
    $student = User::factory()->create(['role' => 'student']);

    $career = Career::create(['name' => 'Engineering']);
    $student->careers()->attach($career);

    $subject = Subject::create([
        'name' => 'Math',
        'career_id' => $career->id,
    ]);

    $teacher = User::factory()->create(['role' => 'teacher']);

    // Create an exam event
    $exam = Event::create([
        'title' => 'Final Exam',
        'start' => Carbon::now()->addDays(2),
        'end' => Carbon::now()->addDays(2)->addHours(2),
        'user_id' => $teacher->id, // Creator
        'subject_id' => $subject->id,
        'presidente_id' => $teacher->id,
    ]);

    Livewire::actingAs($student)
        ->test(UpcomingExams::class)
        ->assertSee('Math')
        ->assertSee($teacher->lastname);
});
