<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('it links an existing tutor to a student with the pivot data', function () {
    $student = User::factory()->create(['role' => 'student']);
    $tutor = User::factory()->create(['role' => 'tutor']);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->set('selectedUserId', $tutor->id)
        ->set('relationship', 'Madre')
        ->set('isEmergencyContact', true)
        ->set('isAuthorizedPickup', false)
        ->set('notes', 'Retira los martes')
        ->call('attachUser')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('student_tutor', [
        'student_id' => $student->id,
        'tutor_id' => $tutor->id,
        'relationship' => 'Madre',
        'is_emergency_contact' => 1,
        'is_authorized_pickup' => 0,
        'notes' => 'Retira los martes',
    ]);
});

test('it links an existing student from the tutors side', function () {
    $tutor = User::factory()->create(['role' => 'tutor']);
    $student = User::factory()->create(['role' => 'student']);

    Volt::actingAs($tutor)
        ->test('users.tutors-manager', ['userId' => $tutor->id])
        ->set('selectedUserId', $student->id)
        ->set('relationship', 'Hijo/a')
        ->call('attachUser')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('student_tutor', [
        'student_id' => $student->id,
        'tutor_id' => $tutor->id,
        'relationship' => 'Hijo/a',
    ]);
});

test('it creates and links a brand new tutor', function () {
    $student = User::factory()->create(['role' => 'student']);
    $admin = User::factory()->create(['role' => 'admin']);

    Volt::actingAs($admin)
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->set('newDni', '30111222')
        ->set('newFirstname', 'Ana')
        ->set('newLastname', 'Martínez')
        ->set('newEmail', 'ana.martinez@example.com')
        ->set('newPhone', '351-5551234')
        ->set('relationship', 'Madre')
        ->call('createAndAttachTutor')
        ->assertHasNoErrors();

    $tutor = User::where('email', 'ana.martinez@example.com')->first();

    expect($tutor)->not->toBeNull()
        ->and($tutor->role)->toBe('tutor')
        ->and($tutor->name)->toBe('30111222')
        ->and($tutor->firstname)->toBe('Ana')
        ->and($tutor->enabled)->toBeTrue();

    $this->assertDatabaseHas('student_tutor', [
        'student_id' => $student->id,
        'tutor_id' => $tutor->id,
        'relationship' => 'Madre',
    ]);
});

test('it validates the new tutor data', function () {
    $student = User::factory()->create(['role' => 'student']);
    $existing = User::factory()->create(['role' => 'tutor']);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->set('newDni', $existing->name)
        ->set('newFirstname', 'Ana')
        ->set('newLastname', 'Martínez')
        ->set('newEmail', $existing->email)
        ->set('relationship', 'Madre')
        ->call('createAndAttachTutor')
        ->assertHasErrors(['newDni', 'newEmail']);

    expect(User::where('email', $existing->email)->count())->toBe(1);
});

test('it detaches a linked tutor', function () {
    $student = User::factory()->create(['role' => 'student']);
    $tutor = User::factory()->create(['role' => 'tutor']);

    $student->tutors()->attach($tutor->id, ['relationship' => 'Padre']);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->call('detachUser', $tutor->id);

    $this->assertDatabaseMissing('student_tutor', [
        'student_id' => $student->id,
        'tutor_id' => $tutor->id,
    ]);
});

test('the search only returns users of the opposite role', function () {
    $student = User::factory()->create(['role' => 'student']);
    $tutor = User::factory()->create(['role' => 'tutor', 'lastname' => 'Martínez']);
    User::factory()->create(['role' => 'teacher', 'lastname' => 'Martínez']);

    $component = Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->set('searchQuery', 'Martí');

    $results = $component->instance()->searchResults();

    expect($results)->toHaveCount(1)
        ->and($results[0]['id'])->toBe($tutor->id);
});

test('the search needs at least two characters', function () {
    $student = User::factory()->create(['role' => 'student']);
    User::factory()->create(['role' => 'tutor', 'lastname' => 'Martínez']);

    $component = Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->set('searchQuery', 'M');

    expect($component->instance()->searchResults())->toBe([]);
});

test('the search excludes tutors already linked to the student', function () {
    $student = User::factory()->create(['role' => 'student']);
    $tutor = User::factory()->create(['role' => 'tutor', 'lastname' => 'Martínez']);

    $student->tutors()->attach($tutor->id, ['relationship' => 'Madre']);

    $component = Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->set('searchQuery', 'Martí');

    expect($component->instance()->searchResults())->toBe([]);
});

test('it lists the linked tutors for a student', function () {
    $student = User::factory()->create(['role' => 'student']);
    $tutor = User::factory()->create([
        'role' => 'tutor',
        'firstname' => 'Ana',
        'lastname' => 'Martínez',
    ]);

    $student->tutors()->attach($tutor->id, [
        'relationship' => 'Madre',
        'is_emergency_contact' => true,
        'is_authorized_pickup' => true,
    ]);

    Volt::actingAs(User::factory()->create(['role' => 'admin']))
        ->test('users.tutors-manager', ['userId' => $student->id])
        ->assertSee('Martínez, Ana')
        ->assertSee('Madre')
        ->assertSee('Emergencia')
        ->assertSee('Retiro autorizado');
});
