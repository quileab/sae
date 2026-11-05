<?php

use App\Models\Career;
use App\Models\Subject;
use App\Models\User;
use App\Services\SubjectOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function subjectIn(Career $career, string $name): Subject
{
    return Subject::create([
        'name' => $name,
        'career_id' => $career->id,
    ]);
}

function optionIds(array $options): array
{
    return array_column($options, 'id');
}

test('administrative roles see every subject', function (string $role) {
    $career = Career::factory()->create();
    $first = subjectIn($career, 'Matemática');
    $second = subjectIn($career, 'Lengua');

    $user = User::factory()->create(['role' => $role]);

    $options = app(SubjectOptions::class)->search($user);

    expect(optionIds($options))->toEqualCanonicalizing([$first->id, $second->id]);
})->with(['admin', 'principal', 'director', 'administrative']);

test('teachers only see the subjects they are assigned to', function () {
    $career = Career::factory()->create();
    $mine = subjectIn($career, 'Matemática');
    subjectIn($career, 'Lengua');

    $teacher = User::factory()->create(['role' => 'teacher']);
    $teacher->subjects()->attach($mine->id);

    $options = app(SubjectOptions::class)->search($teacher);

    expect(optionIds($options))->toBe([$mine->id]);
});

test('the search matches by subject name', function () {
    $career = Career::factory()->create();
    $math = subjectIn($career, 'Matemática');
    subjectIn($career, 'Lengua');

    $admin = User::factory()->create(['role' => 'admin']);

    $options = app(SubjectOptions::class)->search($admin, 'mate');

    expect(optionIds($options))->toBe([$math->id]);
});

test('the search matches by subject id', function () {
    $career = Career::factory()->create();
    $math = subjectIn($career, 'Matemática');
    subjectIn($career, 'Lengua');

    $admin = User::factory()->create(['role' => 'admin']);

    $options = app(SubjectOptions::class)->search($admin, (string) $math->id);

    expect(optionIds($options))->toContain($math->id);
});

test('the search can be limited to a career', function () {
    $firstCareer = Career::factory()->create();
    $secondCareer = Career::factory()->create();
    $mine = subjectIn($firstCareer, 'Matemática');
    subjectIn($secondCareer, 'Lengua');

    $admin = User::factory()->create(['role' => 'admin']);

    $options = app(SubjectOptions::class)->search($admin, '', $firstCareer->id);

    expect(optionIds($options))->toBe([$mine->id]);
});

test('the selected subject is kept even when it does not match the term', function () {
    $career = Career::factory()->create();
    $selected = subjectIn($career, 'Matemática');
    $other = subjectIn($career, 'Lengua');

    $admin = User::factory()->create(['role' => 'admin']);

    $options = app(SubjectOptions::class)->search($admin, 'Lengua', null, [$selected->id]);

    expect(optionIds($options))->toEqualCanonicalizing([$selected->id, $other->id]);
});

test('the selected subject is dropped when the user cannot see it', function () {
    $career = Career::factory()->create();
    $mine = subjectIn($career, 'Matemática');
    $foreign = subjectIn($career, 'Lengua');

    $teacher = User::factory()->create(['role' => 'teacher']);
    $teacher->subjects()->attach($mine->id);

    $options = app(SubjectOptions::class)->search($teacher, '', null, [$foreign->id]);

    expect(optionIds($options))->toBe([$mine->id]);
});

test('options are labelled with the id and the subject name', function () {
    $career = Career::factory()->create();
    $subject = subjectIn($career, 'Matemática');

    $admin = User::factory()->create(['role' => 'admin']);

    $options = app(SubjectOptions::class)->search($admin);

    expect($options)->toBe([
        ['id' => $subject->id, 'name' => $subject->id.' - Matemática'],
    ]);
});

test('the option list is capped to the maximum number of results', function () {
    $career = Career::factory()->create();

    foreach (range(1, SubjectOptions::MAX_RESULTS + 5) as $index) {
        subjectIn($career, 'Materia '.$index);
    }

    $admin = User::factory()->create(['role' => 'admin']);

    expect(app(SubjectOptions::class)->search($admin))->toHaveCount(SubjectOptions::MAX_RESULTS);
});
