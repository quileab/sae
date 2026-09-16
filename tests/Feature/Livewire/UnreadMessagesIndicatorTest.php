<?php

use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('shows correct unread messages count for logged in user', function () {
    $user = User::factory()->create();
    $sender = User::factory()->create();

    // Create a message
    $message = Message::create([
        'sender_id' => $sender->id,
        'content' => 'Test message',
    ]);

    // Mark as unread for the user
    MessageRead::create([
        'message_id' => $message->id,
        'user_id' => $user->id,
        'read_at' => null,
    ]);

    Volt::actingAs($user)
        ->test('unread-messages-indicator')
        ->assertSee('Comunicación')
        ->assertSee('1'); // Should see unread count
});

test('shows no badge when there are no unread messages', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('unread-messages-indicator')
        ->assertSee('Comunicación')
        ->assertDontSee('badge');
});
