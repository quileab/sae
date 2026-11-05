<?php

use App\Livewire\Users\UserForm;
use App\Models\Career;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function userFormData(array $attributes = []): array
{
    return array_merge([
        'id' => '40111222',
        'name' => '40111222',
        'email' => 'nuevo.usuario@example.com',
        'firstname' => 'Lucía',
        'lastname' => 'Gómez',
        'phone' => '351-5550000',
        'birth_date' => '1990-05-20',
        'role' => 'tutor',
        'enabled' => true,
        'status' => 'active',
        'careers' => [],
    ], $attributes);
}

test('an admin can create a tutor with a birth date', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('data', userFormData())
        ->call('save')
        ->assertHasNoErrors();

    $tutor = User::where('email', 'nuevo.usuario@example.com')->first();

    expect($tutor)->not->toBeNull()
        ->and($tutor->role)->toBe('tutor')
        ->and($tutor->birth_date->format('Y-m-d'))->toBe('1990-05-20');
});

test('it normalizes an empty birth date to null', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('data', userFormData(['birth_date' => '']))
        ->call('save')
        ->assertHasNoErrors();

    expect(User::where('email', 'nuevo.usuario@example.com')->value('birth_date'))->toBeNull();
});

test('it rejects a birth date in the future', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('data', userFormData(['birth_date' => now()->addDay()->toDateString()]))
        ->call('save')
        ->assertHasErrors(['data.birth_date']);

    expect(User::where('email', 'nuevo.usuario@example.com')->exists())->toBeFalse();
});

test('it rejects an unknown role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(UserForm::class)
        ->set('data', userFormData(['role' => 'superadmin']))
        ->call('save')
        ->assertHasErrors(['data.role']);

    expect(User::where('email', 'nuevo.usuario@example.com')->exists())->toBeFalse();
});

test('it shows the tutors and medical panels for a student', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['id' => $student->id])
        ->assertSee('Padres / Tutores Vinculados')
        ->assertSee('Ficha Médica y Salud Escolar');
});

test('it shows the students panel instead of the medical one for a tutor', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $tutor = User::factory()->create(['role' => 'tutor']);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['id' => $tutor->id])
        ->assertSee('Estudiantes a Cargo')
        ->assertDontSee('Ficha Médica y Salud Escolar');
});

test('it lists the assigned careers as arrays', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);
    $career = Career::factory()->create(['name' => 'Sistemas']);
    $student->careers()->attach($career);

    Livewire::actingAs($admin)
        ->test(UserForm::class, ['id' => $student->id])
        ->assertSee('Sistemas')
        ->assertSet('data.careers.0.name', 'Sistemas');
});
