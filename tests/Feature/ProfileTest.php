<?php

use App\Livewire\Users\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('unauthenticated user cannot access profile', function () {
    get('/profile')
        ->assertRedirect('/login');
});

test('authenticated user can access profile', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSeeLivewire(Profile::class);
});

test('can update profile information', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    actingAs($user);

    Livewire::test(Profile::class)
        ->set('name', 'New Name')
        ->set('email', 'new@example.com')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
});

test('can update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    actingAs($user);

    Livewire::test(Profile::class)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});

test('password update requires confirmation', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(Profile::class)
        ->set('password', 'new-password')
        ->set('password_confirmation', 'wrong-confirmation')
        ->call('updatePassword')
        ->assertHasErrors(['password' => 'confirmed']);
});
