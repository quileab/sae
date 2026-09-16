<?php

use App\Livewire\ContentManager;
use App\Models\Career;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->career = Career::factory()->create();
    $this->subject = Subject::create([
        'name' => 'Test Subject',
        'career_id' => $this->career->id,
    ]);
});

test('students cannot add units in content manager', function () {
    $student = User::factory()->create(['role' => 'student']);

    // Enroll student
    Enrollment::create([
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'status' => 'active',
    ]);

    Livewire::actingAs($student)
        ->test(ContentManager::class, ['subject' => $this->subject])
        ->call('addUnit')
        ->assertStatus(403);
});

test('staff can add units in content manager', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    // Enroll admin (optional depending on authorizeSubject logic, but let's be safe)
    Enrollment::create([
        'user_id' => $admin->id,
        'subject_id' => $this->subject->id,
        'status' => 'active',
    ]);

    Livewire::actingAs($admin)
        ->test(ContentManager::class, ['subject' => $this->subject])
        ->call('addUnit')
        ->assertOk();
});

test('students cannot import content in content manager', function () {
    $student = User::factory()->create(['role' => 'student']);

    Enrollment::create([
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'status' => 'active',
    ]);

    Livewire::actingAs($student)
        ->test(ContentManager::class, ['subject' => $this->subject])
        ->call('importContent')
        ->assertStatus(403);
});
