<?php

use App\Livewire\Inscriptions\InscriptionManager;
use App\Models\Career;
use App\Models\Config;
use App\Models\Inscription;
use App\Models\Subject;
use App\Models\User;
use App\Services\InscriptionCatalog;
use App\Services\InscriptionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'id' => Inscription::CATALOG_USER_ID,
        'name' => 'admin',
        'role' => 'admin',
        'enabled' => true,
    ]);

    $this->career = Career::factory()->create(['allow_enrollments' => true, 'allow_evaluations' => true]);
    $this->subject = Subject::create([
        'id' => 1001,
        'name' => 'Test Subject',
        'career_id' => $this->career->id,
    ]);

    Config::where('group', 'inscriptions')->delete();

    Config::create([
        'id' => 'insc_2024',
        'group' => 'inscriptions',
        'value' => 'true',
        'description' => 'Inscripción 2024',
        'type' => 'bool',
    ]);

    Inscription::create([
        'subject_id' => $this->subject->id,
        'user_id' => Inscription::CATALOG_USER_ID,
        'configs_id' => 'insc_2024',
        'type' => 'csv-1',
        'value' => '20/07',
    ]);
});

test('students can select a single value from the admin catalog', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.selected', '20/07')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
        'type' => 'csv-1',
        'value' => '20/07',
    ]);
});

test('students can select multiple values when the catalog is csv-n', function () {
    Inscription::where('configs_id', 'insc_2024')
        ->where('user_id', Inscription::CATALOG_USER_ID)
        ->update(['type' => 'csv-n', 'value' => 'A,B,C']);

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.selected', ['A', 'C'])
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
        'type' => 'csv-n',
        'value' => 'A,C',
    ]);
});

test('students cannot save a value that is not in the catalog', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.selected', 'Aprobado')
        ->call('save')
        ->assertHasErrors(['rows.'.$this->subject->id.'.selected']);

    $this->assertDatabaseMissing('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
    ]);
});

test('students cannot save after the period is closed', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    $component = Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.selected', '20/07');

    Config::find('insc_2024')->update(['value' => 'false']);

    $component->call('save')
        ->assertHasErrors(['inscription_id']);

    $this->assertDatabaseMissing('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
    ]);
});

test('students cannot save inscriptions for subjects outside the selected career', function () {
    $otherCareer = Career::factory()->create(['allow_enrollments' => true, 'allow_evaluations' => true]);
    $foreignSubject = Subject::create([
        'id' => 2001,
        'name' => 'Foreign Subject',
        'career_id' => $otherCareer->id,
    ]);

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);
    $student->careers()->attach($otherCareer);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$foreignSubject->id.'.selected', '20/07')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $foreignSubject->id,
        'configs_id' => 'insc_2024',
    ]);
});

test('staff can manage the catalog value and type per subject', function () {
    Livewire::actingAs($this->admin)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.value', 'Presencial,Semipresencial,Libre')
        ->set('rows.'.$this->subject->id.'.type', 'csv-n')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('inscriptions', [
        'user_id' => Inscription::CATALOG_USER_ID,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
        'type' => 'csv-n',
        'value' => 'Presencial,Semipresencial,Libre',
    ]);
});

test('students cannot save values for subjects without catalog', function () {
    $otherSubject = Subject::create([
        'id' => 1002,
        'name' => 'Subject Without Catalog',
        'career_id' => $this->career->id,
    ]);

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$otherSubject->id.'.selected', '20/07')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $otherSubject->id,
        'configs_id' => 'insc_2024',
    ]);
});

test('staff can clear a catalog value', function () {
    Livewire::actingAs($this->admin)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.value', '')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('inscriptions', [
        'user_id' => Inscription::CATALOG_USER_ID,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
    ]);
});

test('staff csv-n values are trimmed and deduplicated when saved', function () {
    Livewire::actingAs($this->admin)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.type', 'csv-n')
        ->set('rows.'.$this->subject->id.'.value', ' A , ,B,A ')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('rows.'.$this->subject->id.'.value', 'A,B');

    $this->assertDatabaseHas('inscriptions', [
        'user_id' => Inscription::CATALOG_USER_ID,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
        'type' => 'csv-n',
        'value' => 'A,B',
    ]);
});

test('students only see subjects that have catalog options', function () {
    $otherSubject = Subject::create([
        'id' => 1002,
        'name' => 'Subject Without Catalog',
        'career_id' => $this->career->id,
    ]);

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    $component = Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ]);

    $ids = $component->instance()->subjectRows->pluck('id')->all();

    expect($ids)->toContain($this->subject->id)
        ->and($ids)->not->toContain($otherSubject->id);
});

test('the selected count and unsaved state follow the student selection', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    $component = Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ]);

    expect($component->instance()->selectedCount)->toBe(0)
        ->and($component->instance()->hasUnsavedChanges())->toBeFalse();

    $component->set('rows.'.$this->subject->id.'.selected', '20/07');

    expect($component->instance()->selectedCount)->toBe(1)
        ->and($component->instance()->hasUnsavedChanges())->toBeTrue();

    $component->call('save')->assertHasNoErrors();

    expect($component->instance()->hasUnsavedChanges())->toBeFalse();
});

test('discarding changes restores the saved selection', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    $component = Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.selected', '20/07');

    expect($component->instance()->hasUnsavedChanges())->toBeTrue();

    $component->call('discardChanges');

    expect($component->instance()->hasUnsavedChanges())->toBeFalse()
        ->and($component->get('rows.'.$this->subject->id.'.selected'))->toBeNull();
});

test('a stale pdf is deleted when the student selection changes', function () {
    Storage::fake('local');

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    Inscription::create([
        'subject_id' => $this->subject->id,
        'user_id' => $student->id,
        'configs_id' => 'insc_2024',
        'type' => 'csv-1',
        'value' => '20/07',
    ]);

    $path = InscriptionPdfService::storagePath($student->id, $this->career->id, 'insc_2024');
    Storage::put($path, 'pdf');

    expect(Storage::exists($path))->toBeTrue();

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->set('rows.'.$this->subject->id.'.selected', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(Storage::exists($path))->toBeFalse();
});

test('subjects are ordered by id', function () {
    Subject::create([
        'id' => 1002,
        'name' => 'AAA First Alphabetically',
        'career_id' => $this->career->id,
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ]);

    expect($component->instance()->subjectRows->pluck('id')->all())
        ->toBe([$this->subject->id, 1002]);
});

test('placeholder catalog values are treated as empty options', function () {
    $catalog = app(InscriptionCatalog::class);

    expect($catalog->options('-----'))->toBe([])
        ->and($catalog->options(' - . - '))->toBe([])
        ->and($catalog->options(' 24/07'))->toBe(['24/07'])
        ->and($catalog->options('A,B'))->toBe(['A', 'B']);
});

test('students do not see subjects with placeholder-only catalog values', function () {
    Inscription::where('configs_id', 'insc_2024')
        ->where('user_id', Inscription::CATALOG_USER_ID)
        ->update(['value' => '-----']);

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    $component = Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ]);

    expect($component->instance()->subjectRows)->toBeEmpty();
});

test('stale student selections are cleared when they are no longer in the catalog', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    Inscription::create([
        'subject_id' => $this->subject->id,
        'user_id' => $student->id,
        'configs_id' => 'insc_2024',
        'type' => 'csv-1',
        'value' => '05/03',
    ]);

    $component = Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ]);

    expect($component->get('rows.'.$this->subject->id.'.selected'))->toBeNull();

    $component->call('save')->assertHasNoErrors();

    $this->assertDatabaseMissing('inscriptions', [
        'user_id' => $student->id,
        'subject_id' => $this->subject->id,
        'configs_id' => 'insc_2024',
    ]);
});

test('student selections are bound live so the summary and actions react', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->assertSeeHtml("entangle('rows.{$this->subject->id}.selected').live");
});

test('confirming with no selections and no changes does not touch the pdf', function () {
    Storage::fake('local');

    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);
    $student->careers()->attach($this->career);

    $path = InscriptionPdfService::storagePath($student->id, $this->career->id, 'insc_2024');
    Storage::put($path, 'pdf');

    Livewire::actingAs($student)
        ->test(InscriptionManager::class, [
            'career_id' => $this->career->id,
            'inscription_id' => 'insc_2024',
        ])
        ->call('saveAndConfirm')
        ->assertReturned(null);

    expect(Storage::exists($path))->toBeTrue();
});

test('unauthorized users cannot access careers they are not in', function () {
    $student = User::factory()->create(['role' => 'student', 'enabled' => true]);

    Livewire::actingAs($student)
        ->test(InscriptionManager::class)
        ->assertSet('career_id', null);
});
