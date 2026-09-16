<?php

use App\Livewire\Students\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->student = User::factory()->create([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'role' => 'student',
    ]);
});

it('renders the component', function () {
    $this->actingAs($this->admin);

    Livewire::test(Search::class)
        ->assertSuccessful();
});

it('can search for students by name', function () {
    $this->actingAs($this->admin);

    Livewire::test(Search::class)
        ->set('search', 'John')
        ->assertSet('users', function ($users) {
            return $users->contains('name', "Doe, John ({$this->student->id})");
        });
});

it('only returns students in search results', function () {
    $this->actingAs($this->admin);
    User::factory()->create(['role' => 'teacher', 'firstname' => 'John']);

    Livewire::test(Search::class)
        ->set('search', 'John')
        ->assertCount('users', 1);
});

it('can search for students by id', function () {
    $this->actingAs($this->admin);

    Livewire::test(Search::class)
        ->set('search', $this->student->id)
        ->assertSet('users', function ($users) {
            return $users->contains('id', $this->student->id);
        });
});

it('redirects to user payments when a student is selected', function () {
    $this->actingAs($this->admin);

    Livewire::test(Search::class)
        ->call('selectUser', $this->student->id)
        ->assertRedirect(route('user-payments', ['user' => $this->student->id]));
});
