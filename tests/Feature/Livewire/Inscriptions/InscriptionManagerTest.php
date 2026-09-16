<?php

use App\Livewire\Inscriptions\InscriptionManager;
use App\Models\Career;
use App\Models\Config;
use App\Models\Inscription;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->career = Career::factory()->create(['allow_enrollments' => true, 'allow_evaluations' => true]);
    $this->subject = Subject::create([
        'id' => rand(1000, 9999),
        'name' => 'Test Subject',
        'career_id' => $this->career->id,
    ]);

    // Cleanup any existing config with this group if RefreshDatabase is behaving weirdly
    Config::where('group', 'inscriptions')->delete();

    // Create inscription config
    Config::create([
        'id' => 'insc_2024',
        'group' => 'inscriptions',
        'value' => 'true',
        'description' => 'Inscripción 2024',
        'type' => 'bool',
    ]);

    // Create admin user for admin_id logic in boot()
    $this->admin = User::factory()->create(['name' => 'admin', 'role' => 'admin']);
});

test('students can view and select inscriptions', function () {
    $student = User::factory()->create(['role' => 'student']);
    $student->careers()->attach($this->career);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('subjects.'.$this->subject->id.'.selected', 'confirmado')
        ->call('save')
        ->assertOk();

    $this->assertDatabaseHas('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
        'value' => 'confirmado',
    ]);
});

test('staff can manage global subject values', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('subjects.'.$this->subject->id.'.value', 'cupo_lleno')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inscriptions', [
        'user_id' => $this->admin->id,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
        'value' => 'cupo_lleno',
    ]);
});

test('unauthorized users cannot access careers they are not in', function () {
    $student = User::factory()->create(['role' => 'student']);
    // Not attached to career

    Livewire::actingAs($student)
        ->test(InscriptionManager::class)
        ->assertSet('career_id', null); // Should not auto-select if no careers available
});
