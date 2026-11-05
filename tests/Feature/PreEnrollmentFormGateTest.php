<?php

use App\Livewire\PreEnrollments\PublicForm;
use App\Models\PreEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

/**
 * Habilita o cierra el formulario público de preinscripción.
 */
function setPreEnrollmentOpen(bool $open): void
{
    DB::table('configs')->updateOrInsert(
        ['id' => 'pre_enrollment_open'],
        [
            'group' => 'pre_enrollment',
            'description' => 'Preinscripción abierta (formulario público)',
            'type' => 'bool',
            'value' => $open ? 'true' : 'false',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    cache()->forget('config.pre_enrollment_open');
}

test('the secondary form is available by default', function () {
    $this->get(route('preinsc.secundario'))
        ->assertSuccessful()
        ->assertSee('Complete el formulario')
        ->assertDontSee('La inscripción online está cerrada');
});

test('the secondary form shows a closed notice when pre-enrollment is closed', function () {
    setPreEnrollmentOpen(false);

    $this->get(route('preinsc.secundario'))
        ->assertSuccessful()
        ->assertSee('La inscripción online está cerrada')
        ->assertSee('rv8206@gmail.com')
        ->assertDontSee('Complete el formulario');
});

test('the secondary form does not create a pre-enrollment when it is closed', function () {
    setPreEnrollmentOpen(false);

    Volt::test('pre-enrollments.secondary-form')
        ->set('doc_number', '30123456')
        ->set('fname', 'Juan')
        ->set('lname', 'Pérez')
        ->set('email', 'juan@example.com')
        ->set('phone', '3482550035')
        ->set('fnacimiento', '2008-05-14')
        ->set('dirCity', 'Avellaneda')
        ->set('escuela', 'Escuela Nº 1')
        ->set('division', 'A')
        ->call('save')
        ->assertSet('closed', true);

    expect(PreEnrollment::count())->toBe(0);
});

test('the generic form shows a closed notice when pre-enrollment is closed', function () {
    setPreEnrollmentOpen(false);

    $this->get(route('preinsc'))
        ->assertSuccessful()
        ->assertSee('La inscripción online está cerrada')
        ->assertDontSee('Elegí tu carrera');
});

test('the generic form does not create a pre-enrollment when it is closed', function () {
    setPreEnrollmentOpen(false);

    Livewire::test(PublicForm::class)
        ->assertSet('closed', true)
        ->call('save')
        ->assertSet('closed', true);

    expect(PreEnrollment::count())->toBe(0);
});
