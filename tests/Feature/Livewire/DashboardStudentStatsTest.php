<?php

use App\Livewire\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('administrative roles see the student statistics panel', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertSee('Estadísticas de Estudiantes');
})->with(['admin', 'administrative', 'director', 'principal']);

test('non administrative staff do not see the student statistics panel', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $component = Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertDontSee('Estadísticas de Estudiantes');

    expect($component->instance()->studentStats)
        ->toBe([
            'total' => 0,
            'without_career' => 0,
            'with_attendance' => 0,
            'enabled' => 0,
            'disabled' => 0,
        ]);
})->with(['teacher', 'student', 'preceptor', 'treasurer']);

test('tutor is redirected away from the dashboard and never sees student statistics', function () {
    $tutor = User::factory()->create(['role' => 'tutor']);

    Livewire::actingAs($tutor)
        ->test(Dashboard::class)
        ->assertRedirect(route('family.portal'));
});
