<?php

use App\Models\Career;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('teacher can sync attendance for students in their career', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $career = Career::create(['name' => 'Valid Career']);
    $teacher->careers()->attach($career);

    $student = User::factory()->create(['role' => 'student']);
    $student->careers()->attach($career);

    $this->actingAs($teacher);

    $response = $this->postJson('/pwa-attendance/sync', [
        'records' => [
            [
                'career_id' => $career->id,
                'user_id' => $student->id,
                'date' => now()->toDateString(),
                'status' => 'present',
                'note' => 'Test note',
            ],
        ],
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('daily_attendances', [
        'career_id' => $career->id,
        'user_id' => $student->id,
        'status' => 'present',
    ]);
});

test('attendance sync skips students not enrolled in the career', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $career = Career::create(['name' => 'Valid Career']);
    $teacher->careers()->attach($career);

    // Student NOT in the career
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($teacher);

    $response = $this->postJson('/pwa-attendance/sync', [
        'records' => [
            [
                'career_id' => $career->id,
                'user_id' => $student->id,
                'date' => now()->toDateString(),
                'status' => 'present',
            ],
        ],
    ]);

    $response->assertSuccessful();
    $response->assertJson(['synced' => 0]); // Should be 0 because it's skipped
    $this->assertDatabaseEmpty('daily_attendances');
});

test('attendance sync rejects records for careers teacher does not have access to', function () {
    $teacher = User::factory()->create(['role' => 'teacher']);
    $otherCareer = Career::create(['name' => 'Other Career']);

    $student = User::factory()->create(['role' => 'student']);
    $student->careers()->attach($otherCareer);

    $this->actingAs($teacher);

    $response = $this->postJson('/pwa-attendance/sync', [
        'records' => [
            [
                'career_id' => $otherCareer->id,
                'user_id' => $student->id,
                'date' => now()->toDateString(),
                'status' => 'present',
            ],
        ],
    ]);

    $response->assertSuccessful();
    $response->assertJson(['synced' => 0]);
    $this->assertDatabaseEmpty('daily_attendances');
});
