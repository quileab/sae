<?php

use App\Models\Book;
use App\Models\Career;
use App\Models\ClassSession;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('user has many books', function () {
    $user = User::factory()->create();
    $book = Book::create([
        'title' => 'Test Book',
        'user_id' => $user->id,
        'publisher' => 'Test Publisher',
        'author' => 'Test Author',
        'gender' => 'Test Gender',
        'extent' => 100,
        'edition' => now(),
        'isbn' => '1234567890',
        'container' => 'Box 1',
        'signature' => 'SIG1',
        'digital' => 'No',
        'origin' => 'Purchase',
        'date_added' => now(),
        'price' => 10.00,
    ]);

    expect($user->books)->toHaveCount(1);
    expect($user->books->first()->id)->toBe($book->id);
});

test('grade belongs to a class session', function () {
    $career = Career::create(['name' => 'Test Career']);
    $subject = Subject::create(['name' => 'Math', 'career_id' => $career->id]);
    $classSession = ClassSession::create([
        'subject_id' => $subject->id,
        'date' => now(),
        'class_number' => 1,
        'unit' => 'Unit 1',
        'type' => 'Theory',
        'content' => 'Introduction',
    ]);

    $user = User::factory()->create();
    $grade = Grade::create([
        'user_id' => $user->id,
        'class_session_id' => $classSession->id,
        'grade' => 10,
        'approved' => true,
        'attendance' => 100,
    ]);

    expect($grade->classSession->id)->toBe($classSession->id);
    expect($grade->classSession->subject->id)->toBe($subject->id);
});

test('user has many subjects through enrollments', function () {
    $user = User::factory()->create();
    $career = Career::create(['name' => 'Test Career 2']);
    $subject = Subject::create(['name' => 'History', 'career_id' => $career->id]);

    $user->subjects()->attach($subject->id);

    expect($user->subjects)->toHaveCount(1);
    expect($user->subjects->first()->name)->toBe('History');
});
