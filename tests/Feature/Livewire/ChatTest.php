<?php

use App\Livewire\Chat;
use App\Models\Career;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin sees course filter when recipient type is user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $career = Career::create(['name' => 'Engineering']);
    $subject = Subject::create(['name' => 'Math', 'career_id' => $career->id]);

    // We need to associate the subject with the admin so it appears in the list?
    // In Chat.php mount(): $subjects = $user->subjects()->with('career')->get();
    // For admin, it seems they only see subjects they are enrolled in?
    // Let's check Chat.php mount again.

    // mount():
    // $subjects = $user->subjects()->with('career')->get();

    // Wait, if admins are not enrolled in subjects, they won't see any subjects in the filter?
    // This might be another issue or intended behavior.
    // Assuming admin is enrolled for the test.
    $admin->subjects()->attach($subject->id);

    Livewire::actingAs($admin)
        ->test(Chat::class)
        ->set('activeTab', 'new')
        ->set('recipient_type', 'user')
        ->assertSee('wire:model.live="selectedSubjectId"', false); // Check for the filter
});

test('admin does not see course filter when recipient type is subject', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $career = Career::create(['name' => 'Engineering']);
    $subject = Subject::create(['name' => 'Math', 'career_id' => $career->id]);
    $admin->subjects()->attach($subject->id);

    Livewire::actingAs($admin)
        ->test(Chat::class)
        ->set('activeTab', 'new')
        ->set('recipient_type', 'subject')
        ->assertDontSee('wire:model.live="selectedSubjectId"', false) // Filter should be gone
        ->assertSee('wire:model.defer="recipient_id"', false); // Recipient picker should be there
});
