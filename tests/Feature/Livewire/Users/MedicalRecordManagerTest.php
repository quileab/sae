<?php

use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

test('it loads the existing medical record into the form fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    MedicalRecord::create([
        'user_id' => $student->id,
        'blood_type' => 'A+',
        'healthcare_provider' => 'OSDE',
        'allergies' => 'Penicilina',
        'has_physical_fitness_certificate' => true,
        'physical_fitness_expires_at' => '2027-03-15',
    ]);

    Volt::actingAs($admin)
        ->test('users.medical-record-manager', ['userId' => $student->id])
        ->assertSet('blood_type', 'A+')
        ->assertSet('healthcare_provider', 'OSDE')
        ->assertSet('allergies', 'Penicilina')
        ->assertSet('has_physical_fitness_certificate', true)
        ->assertSet('physical_fitness_expires_at', '2027-03-15');
});

test('it creates a medical record the first time the form is saved', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    Volt::actingAs($admin)
        ->test('users.medical-record-manager', ['userId' => $student->id])
        ->set('blood_type', '0-')
        ->set('healthcare_provider', 'Swiss Medical')
        ->set('emergency_contact_name', 'Marta Pérez')
        ->set('emergency_contact_phone', '351-1234567')
        ->set('allergies', 'Frutos secos')
        ->call('save')
        ->assertHasNoErrors();

    expect(MedicalRecord::where('user_id', $student->id)->count())->toBe(1);

    $record = $student->fresh()->medicalRecord;

    expect($record->blood_type)->toBe('0-')
        ->and($record->healthcare_provider)->toBe('Swiss Medical')
        ->and($record->emergency_contact_name)->toBe('Marta Pérez')
        ->and($record->allergies)->toBe('Frutos secos');
});

test('it updates the existing record instead of creating a duplicate', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    MedicalRecord::create([
        'user_id' => $student->id,
        'healthcare_provider' => 'IOMA',
        'allergies' => 'Ninguna',
    ]);

    Volt::actingAs($admin)
        ->test('users.medical-record-manager', ['userId' => $student->id])
        ->set('allergies', 'Ibuprofeno')
        ->set('chronic_conditions', 'Asma')
        ->call('save')
        ->assertHasNoErrors();

    expect(MedicalRecord::where('user_id', $student->id)->count())->toBe(1);

    $record = $student->fresh()->medicalRecord;

    expect($record->healthcare_provider)->toBe('IOMA')
        ->and($record->allergies)->toBe('Ibuprofeno')
        ->and($record->chronic_conditions)->toBe('Asma');
});

test('it stores empty optional fields as null', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    Volt::actingAs($admin)
        ->test('users.medical-record-manager', ['userId' => $student->id])
        ->set('blood_type', '')
        ->set('observations', '')
        ->call('save')
        ->assertHasNoErrors();

    $record = $student->fresh()->medicalRecord;

    expect($record->blood_type)->toBeNull()
        ->and($record->observations)->toBeNull();
});

test('it validates the medical record field formats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    Volt::actingAs($admin)
        ->test('users.medical-record-manager', ['userId' => $student->id])
        ->set('blood_type', str_repeat('A', 11))
        ->set('physical_fitness_expires_at', 'not-a-date')
        ->call('save')
        ->assertHasErrors(['blood_type', 'physical_fitness_expires_at']);

    expect(MedicalRecord::where('user_id', $student->id)->exists())->toBeFalse();
});

test('it forbids self management of another users medical record', function () {
    $intruder = User::factory()->create(['role' => 'student']);
    $victim = User::factory()->create(['role' => 'student']);

    Volt::actingAs($intruder)
        ->test('users.medical-record-manager', [
            'userId' => $victim->id,
            'isSelfManagement' => true,
        ])
        ->set('observations', 'Intento no autorizado')
        ->call('save')
        ->assertForbidden();

    expect(MedicalRecord::where('user_id', $victim->id)->exists())->toBeFalse();
});

test('it allows an adult student to self manage their own record', function () {
    $student = User::factory()->create([
        'role' => 'student',
        'birth_date' => now()->subYears(20)->toDateString(),
    ]);

    Volt::actingAs($student)
        ->test('users.medical-record-manager', [
            'userId' => $student->id,
            'isSelfManagement' => true,
        ])
        ->set('healthcare_provider', 'OSDE')
        ->call('save')
        ->assertHasNoErrors();

    expect(MedicalRecord::where('user_id', $student->id)->value('healthcare_provider'))
        ->toBe('OSDE');
});

test('it shows the physical fitness certificate status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $student = User::factory()->create(['role' => 'student']);

    Volt::actingAs($admin)
        ->test('users.medical-record-manager', ['userId' => $student->id])
        ->assertSee('Sin Apto Físico')
        ->set('has_physical_fitness_certificate', true)
        ->set('physical_fitness_expires_at', now()->addYear()->toDateString())
        ->assertSee('Apto Físico Vigente')
        ->set('physical_fitness_expires_at', now()->subDay()->toDateString())
        ->assertSee('Apto Físico Vencido');
});
