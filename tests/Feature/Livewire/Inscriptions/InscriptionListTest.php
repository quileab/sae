<?php

use App\Livewire\Inscriptions\InscriptionList;
use App\Models\Career;
use App\Models\Config;
use App\Models\Inscription;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the list excludes the admin catalog rows', function () {
    $admin = User::factory()->create([
        'id' => Inscription::CATALOG_USER_ID,
        'name' => 'admin',
        'role' => 'admin',
        'enabled' => true,
    ]);

    $career = Career::factory()->create(['allow_enrollments' => true, 'allow_evaluations' => true]);
    $subject = Subject::create([
        'id' => 1001,
        'name' => 'Test Subject',
        'career_id' => $career->id,
    ]);

    Config::create([
        'id' => 'insc_2024',
        'group' => 'inscriptions',
        'value' => 'true',
        'description' => 'Inscripción 2024',
        'type' => 'bool',
    ]);

    Inscription::create([
        'subject_id' => $subject->id,
        'user_id' => Inscription::CATALOG_USER_ID,
        'configs_id' => 'insc_2024',
        'type' => 'csv-1',
        'value' => '20/07',
    ]);

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);

    Inscription::create([
        'subject_id' => $subject->id,
        'user_id' => $student->id,
        'configs_id' => 'insc_2024',
        'type' => 'csv-1',
        'value' => '20/07',
    ]);

    $component = Livewire::actingAs($admin)->test(InscriptionList::class);

    $items = $component->instance()->items();

    expect($items)->toHaveCount(1)
        ->and($items->first()->user_id)->toBe($student->id)
        ->and($items->first()->pdf_exists)->toBeFalse();
});
