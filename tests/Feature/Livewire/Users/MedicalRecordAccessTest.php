<?php

use App\Models\Career;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff can always manage the medical record', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->canManageOwnMedicalRecord())->toBeTrue();
})->with(['admin', 'teacher', 'preceptor', 'director']);

test('an adult student can manage their own medical record', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'birth_date' => now()->subYears(20)->toDateString(),
    ]);

    expect($student->isAdult())->toBeTrue()
        ->and($student->canManageOwnMedicalRecord())->toBeTrue();
});

test('a tertiary student under age can manage their own medical record', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'birth_date' => now()->subYears(16)->toDateString(),
    ]);
    $student->careers()->attach(Career::factory()->create(['level' => 'tertiary']));

    expect($student->isAdult())->toBeFalse()
        ->and($student->isTertiaryStudent())->toBeTrue()
        ->and($student->canManageOwnMedicalRecord())->toBeTrue();
});

test('a secondary student under age cannot manage their own medical record', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'birth_date' => now()->subYears(15)->toDateString(),
    ]);
    $student->careers()->attach(Career::factory()->create(['level' => 'secondary']));

    expect($student->isAdult())->toBeFalse()
        ->and($student->isTertiaryStudent())->toBeFalse()
        ->and($student->canManageOwnMedicalRecord())->toBeFalse();
});

test('a secondary student without birth date cannot manage their own medical record', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'birth_date' => null,
    ]);
    $student->careers()->attach(Career::factory()->create(['level' => 'secondary']));

    expect($student->isAdult())->toBeFalse()
        ->and($student->canManageOwnMedicalRecord())->toBeFalse();
});

test('a user without careers defaults to self management autonomy', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'birth_date' => now()->subYears(15)->toDateString(),
    ]);

    expect($student->canManageOwnMedicalRecord())->toBeTrue();
});

test('a tutor is recognized as such and is not staff', function () {
    $tutor = User::factory()->create(['role' => 'tutor']);

    expect($tutor->isTutor())->toBeTrue()
        ->and($tutor->isStaff())->toBeFalse();
});

test('the student tutor relation exposes the pivot data in both directions', function () {
    $student = User::factory()->create(['role' => 'student']);
    $tutor = User::factory()->create(['role' => 'tutor']);

    $student->tutors()->attach($tutor->id, [
        'relationship' => 'Madre',
        'is_emergency_contact' => true,
        'is_authorized_pickup' => true,
    ]);

    $linkedTutor = $student->tutors()->first();
    $linkedStudent = $tutor->students()->first();

    expect($linkedTutor->id)->toBe($tutor->id)
        ->and($linkedTutor->pivot->relationship)->toBe('Madre')
        ->and($linkedTutor->pivot->is_authorized_pickup)->toBe(1)
        ->and($linkedStudent->id)->toBe($student->id);
});
