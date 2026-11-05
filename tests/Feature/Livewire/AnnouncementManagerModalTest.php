<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('the new circular modal hides its subtitle', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Volt::actingAs($admin)
        ->test('announcements.announcement-manager')
        ->set('showCreateModal', true)
        ->assertSee('Nueva circular')
        ->assertDontSee('Redacta el mensaje y elige a quién llega');
});

test('the acknowledgment row keeps the expiration field compact and labeled', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Volt::actingAs($admin)
        ->test('announcements.announcement-manager')
        ->set('showCreateModal', true)
        ->assertSee('Pedir acuse de recibo')
        ->assertSee('Vence el (opcional)')
        ->assertDontSee('Opcional');
});
