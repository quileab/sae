<?php

use App\Livewire\Dashboard;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAnnouncementForDashboard(User $sender, array $attributes = []): Event
{
    return Event::create(array_merge([
        'type' => 'announcement',
        'user_id' => $sender->id,
        'title' => 'Circular de prueba',
        'description' => 'Contenido de la circular de prueba.',
        'priority' => 'normal',
        'target' => 'all',
        'target_roles' => null,
        'requires_acknowledgment' => false,
        'published_at' => now(),
    ], $attributes));
}

test('dashboard shows the announcements section with a link to the wall', function () {
    $student = User::factory()->create(['role' => 'student']);

    Livewire::actingAs($student)
        ->test(Dashboard::class)
        ->assertSee('Circulares')
        ->assertSee('Ver muro')
        ->assertSee(route('announcements.index'), false);
});

test('dashboard lists published announcements visible to the user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    createAnnouncementForDashboard($admin, ['title' => 'Reunión general de familias']);

    Livewire::actingAs($student)
        ->test(Dashboard::class)
        ->assertSee('Reunión general de familias');
});

test('dashboard hides expired announcements', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    createAnnouncementForDashboard($admin, [
        'title' => 'Circular expirada',
        'expires_at' => now()->subDay(),
    ]);

    Livewire::actingAs($student)
        ->test(Dashboard::class)
        ->assertDontSee('Circular expirada');
});
