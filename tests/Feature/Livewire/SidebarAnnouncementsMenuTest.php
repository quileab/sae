<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin sees a single announcements entry in the sidebar', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('profile'));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'Circulares'))->toBe(1);
});

test('students still see the announcements link in the sidebar', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)->get(route('profile'));

    $response->assertOk()->assertSee('Circulares');
    expect(substr_count($response->getContent(), 'Circulares'))->toBe(1);
});
