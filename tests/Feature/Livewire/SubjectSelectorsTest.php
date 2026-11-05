<?php

use App\Livewire\Chat;
use App\Livewire\ClassSessions\ClassSessionList;
use App\Livewire\ContentManager;
use App\Livewire\Dashboard;
use App\Livewire\Inscriptions\InscriptionList;
use App\Models\Career;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    session()->forget('current_subject_id');

    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->teacher = User::factory()->create(['role' => 'teacher']);

    $this->career = Career::factory()->create([
        'allow_enrollments' => true,
        'allow_evaluations' => true,
    ]);

    $this->subject = Subject::create([
        'name' => 'Matemática',
        'career_id' => $this->career->id,
    ]);

    $this->otherSubject = Subject::create([
        'name' => 'Lengua',
        'career_id' => $this->career->id,
    ]);
});

function selectorOptionIds($component): array
{
    return array_column($component->get('subjectOptions'), 'id');
}

test('the admin dashboard offers every subject, even the ones they are not enrolled in', function () {
    $component = Livewire::actingAs($this->admin)->test(Dashboard::class);

    expect(selectorOptionIds($component))
        ->toEqualCanonicalizing([$this->subject->id, $this->otherSubject->id]);

    $component->call('searchSubjects', 'Lengua');

    expect(selectorOptionIds($component))->toBe([$this->otherSubject->id]);
});

test('the dashboard keeps the current subject when a search does not match it', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(Dashboard::class)
        ->set('subject_id', $this->subject->id)
        ->call('searchSubjects', 'Lengua');

    expect(selectorOptionIds($component))->toContain($this->subject->id);
});

test('an admin can open the content manager of a subject they are not enrolled in', function () {
    Livewire::actingAs($this->admin)
        ->test(ContentManager::class, ['subject' => $this->subject])
        ->assertSet('subject_id', $this->subject->id);
});

test('a teacher cannot open the content manager of a subject they do not teach', function () {
    Livewire::actingAs($this->teacher)
        ->test(ContentManager::class, ['subject' => $this->subject])
        ->assertForbidden();
});

test('the class session list offers every subject to the admin and searches them', function () {
    $component = Livewire::actingAs($this->admin)->test(ClassSessionList::class);

    expect(selectorOptionIds($component))
        ->toEqualCanonicalizing([$this->subject->id, $this->otherSubject->id]);

    $component->set('subject_id', null)->call('searchSubjects', 'Mate');

    expect(selectorOptionIds($component))->toBe([$this->subject->id]);
});

test('the evaluations screen offers every subject to the admin and searches them', function () {
    $component = Volt::actingAs($this->admin)->test('evaluations.index');

    expect(array_column($component->get('subjects'), 'id'))
        ->toEqualCanonicalizing([$this->subject->id, $this->otherSubject->id]);

    $component->set('subject_id', null)->call('searchSubjects', 'Mate');

    expect(array_column($component->get('subjects'), 'id'))->toBe([$this->subject->id]);
});

test('the chat subject picker searches every subject and keeps the selected recipient', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(Chat::class)
        ->set('activeTab', 'new')
        ->set('recipient_type', 'subject')
        ->set('recipient_id', $this->subject->id)
        ->call('searchSubjects', 'Lengua');

    $ids = array_column($component->get('subjects'), 'id');

    expect($ids)->toContain($this->otherSubject->id)
        ->and($ids)->toContain($this->subject->id);
});

test('a principal sees every subject in the chat picker', function () {
    $principal = User::factory()->create(['role' => 'principal']);

    $component = Livewire::actingAs($principal)
        ->test(Chat::class)
        ->set('activeTab', 'new')
        ->set('recipient_type', 'subject')
        ->set('recipient_id', null)
        ->call('searchSubjects', 'Lengua');

    expect(array_column($component->get('subjects'), 'id'))->toBe([$this->otherSubject->id]);
});

test('the inscription list searches the subjects of the selected career', function () {
    $component = Livewire::actingAs($this->admin)->test(InscriptionList::class);

    $component->call('searchSubjects', 'Lengua');

    expect(array_column($component->get('subjects'), 'id'))->toBe([$this->otherSubject->id]);
});

test('the risk report searches the subjects of the selected career', function () {
    $component = Volt::actingAs($this->admin)
        ->test('students.risk-report')
        ->set('career_id', $this->career->id)
        ->call('searchSubjects', 'Mate');

    expect(selectorOptionIds($component))->toBe([$this->subject->id]);
});
