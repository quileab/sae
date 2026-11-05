<?php

use App\Enums\PreEnrollmentStatus;
use App\Models\Career;
use App\Models\PreEnrollment;
use App\Models\PreEnrollmentMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function preEnrollmentStatusUrl(string $dni, ?string $birthdate = '2008-05-14'): string
{
    return '/api/public/pre-enrollments/status?'.http_build_query(array_filter([
        'dni' => $dni,
        'birthdate' => $birthdate,
    ]));
}

beforeEach(function () {
    config(['services.landing.api_key' => 'testing-landing-key']);
    $this->withHeaders(['X-Api-Key' => 'testing-landing-key']);
});

test('maps each enrollment status to the public group', function (PreEnrollmentStatus $status, string $group) {
    PreEnrollment::factory()->status($status)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJson([
            'found' => true,
            'group' => $group,
            'status' => $status->value,
            'label' => $status->label(),
        ]);
})->with([
    'validated' => [PreEnrollmentStatus::Validated, 'accepted'],
    'enrolled' => [PreEnrollmentStatus::Enrolled, 'accepted'],
    'waitlisted' => [PreEnrollmentStatus::Waitlisted, 'waitlist'],
    'rejected' => [PreEnrollmentStatus::Rejected, 'rejected'],
    'submitted' => [PreEnrollmentStatus::Submitted, 'in_process'],
    'observed' => [PreEnrollmentStatus::Observed, 'in_process'],
    'draft' => [PreEnrollmentStatus::Draft, 'in_process'],
]);

test('includes a signed materials url when the pre-enrollment is accepted', function (PreEnrollmentStatus $status) {
    PreEnrollment::factory()->status($status)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJsonPath('group', 'accepted')
        ->assertJsonPath('materials_url', fn ($url) => is_string($url) && str_contains($url, '/preinsc/materiales/'));
})->with([
    'validated' => [PreEnrollmentStatus::Validated],
    'enrolled' => [PreEnrollmentStatus::Enrolled],
]);

test('does not include a materials url when the pre-enrollment is not accepted', function (PreEnrollmentStatus $status) {
    PreEnrollment::factory()->status($status)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJsonPath('group', 'in_process')
        ->assertJsonPath('materials_url', null);
})->with([
    'submitted' => [PreEnrollmentStatus::Submitted],
    'observed' => [PreEnrollmentStatus::Observed],
    'draft' => [PreEnrollmentStatus::Draft],
]);

test('lists the in-scope materials with signed download urls when accepted', function () {
    $pre = PreEnrollment::factory()->status(PreEnrollmentStatus::Validated)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $pre->cycle_id, 'career_id' => null, 'title' => 'Material general',
    ]);
    PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $pre->cycle_id, 'career_id' => $pre->career_id, 'title' => 'Material de mi carrera',
    ]);
    PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $pre->cycle_id, 'career_id' => Career::factory()->create()->id, 'title' => 'Material de otra carrera',
    ]);
    PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $pre->cycle_id + 1, 'career_id' => $pre->career_id, 'title' => 'Material de otro ciclo',
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJsonCount(2, 'materials')
        ->assertJsonPath('materials.0.title', 'Material de mi carrera')
        ->assertJsonPath('materials.0.original_name', fn ($name) => is_string($name) && $name !== '')
        ->assertJsonPath('materials.0.download_url', fn ($url) => is_string($url) && str_contains($url, '/preinsc/materiales/'))
        ->assertJsonPath('materials.1.title', 'Material general')
        ->assertJsonMissingPath('materials.1.file_path');
});

test('returns an empty materials list when the pre-enrollment is not accepted', function () {
    PreEnrollment::factory()->status(PreEnrollmentStatus::Submitted)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJsonPath('materials', []);
});

test('returns the student name, career and cycle when the data matches', function () {
    $pre = PreEnrollment::factory()->status(PreEnrollmentStatus::Validated)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJson([
            'found' => true,
            'student_name' => 'Pérez, Juan',
            'career' => $pre->career->name,
            'cycle' => $pre->cycle_id,
        ]);
});

test('normalizes the dni removing non digit characters', function () {
    PreEnrollment::factory()->status(PreEnrollmentStatus::Validated)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30.123.456'))
        ->assertSuccessful()
        ->assertJson(['found' => true]);
});

test('returns not found when the birthdate does not match', function () {
    PreEnrollment::factory()->status(PreEnrollmentStatus::Validated)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456', '1999-01-01'))
        ->assertSuccessful()
        ->assertJson(['found' => false]);
});

test('returns not found when the dni is unknown', function () {
    $this->getJson(preEnrollmentStatusUrl('99999999'))
        ->assertSuccessful()
        ->assertJson(['found' => false]);
});

test('returns not found when the stored payload has no birthdate', function () {
    PreEnrollment::factory()->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJson(['found' => false]);
});

test('does not leak sensitive fields', function () {
    PreEnrollment::factory()->status(PreEnrollmentStatus::Validated)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    $this->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertSuccessful()
        ->assertJsonMissingPath('id')
        ->assertJsonMissingPath('email')
        ->assertJsonMissingPath('phone')
        ->assertJsonMissingPath('payload')
        ->assertJsonMissingPath('doc_number');
});

test('rejects an invalid dni', function (string $dni) {
    $this->getJson(preEnrollmentStatusUrl($dni))
        ->assertStatus(422);
})->with(['too short' => ['123'], 'not numeric' => ['abcdefgh']]);

test('requires the birthdate', function () {
    $this->getJson('/api/public/pre-enrollments/status?dni=30123456')
        ->assertStatus(422);
});

test('rejects requests without the api key', function () {
    $this->flushHeaders()
        ->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertUnauthorized();
});

test('rejects requests with a wrong api key', function () {
    $this->withHeaders(['X-Api-Key' => 'wrong-key'])
        ->getJson(preEnrollmentStatusUrl('30123456'))
        ->assertUnauthorized();
});

test('throttles requests for the same dni', function () {
    PreEnrollment::factory()->status(PreEnrollmentStatus::Validated)->create([
        'doc_number' => '30123456',
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);

    foreach (range(1, 5) as $ignored) {
        $this->getJson(preEnrollmentStatusUrl('30123456'))->assertSuccessful();
    }

    $this->getJson(preEnrollmentStatusUrl('30123456'))->assertStatus(429);
});
