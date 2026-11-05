<?php

use App\Enums\PreEnrollmentStatus;
use App\Models\Career;
use App\Models\PreEnrollment;
use App\Models\PreEnrollmentMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function acceptedPreEnrollment(): PreEnrollment
{
    return PreEnrollment::factory()->status(PreEnrollmentStatus::Validated)->create([
        'payload' => ['fname' => 'Juan', 'lname' => 'Pérez', 'fnacimiento' => '2008-05-14'],
    ]);
}

function signedMaterialsUrl(PreEnrollment $preEnrollment): string
{
    return URL::temporarySignedRoute('preinsc.materials', now()->addDay(), [
        'preEnrollment' => $preEnrollment->getKey(),
    ]);
}

test('accepted students can see the materials page through a signed link', function () {
    $preEnrollment = acceptedPreEnrollment();
    PreEnrollmentMaterial::factory()->create(['cycle_id' => $preEnrollment->cycle_id, 'title' => 'Normas de convivencia']);

    $this->get(signedMaterialsUrl($preEnrollment))
        ->assertSuccessful()
        ->assertSee('Materiales para inscriptos')
        ->assertSee('Normas de convivencia');
});

test('the materials page requires a valid signature', function () {
    $preEnrollment = acceptedPreEnrollment();

    $this->get('/preinsc/materiales/'.$preEnrollment->getKey())->assertForbidden();
});

test('non accepted students cannot see the materials page', function () {
    $preEnrollment = PreEnrollment::factory()->status(PreEnrollmentStatus::Submitted)->create();

    $this->get(signedMaterialsUrl($preEnrollment))->assertNotFound();
});

test('the materials page only lists general and same career materials of the cycle', function () {
    $preEnrollment = acceptedPreEnrollment();

    $general = PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $preEnrollment->cycle_id,
        'career_id' => null,
        'title' => 'Material general',
    ]);
    $ownCareer = PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $preEnrollment->cycle_id,
        'career_id' => $preEnrollment->career_id,
        'title' => 'Material de mi carrera',
    ]);
    $otherCareer = PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $preEnrollment->cycle_id,
        'career_id' => Career::factory()->create()->id,
        'title' => 'Material de otra carrera',
    ]);
    $otherCycle = PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $preEnrollment->cycle_id + 1,
        'career_id' => $preEnrollment->career_id,
        'title' => 'Material de otro ciclo',
    ]);

    $this->get(signedMaterialsUrl($preEnrollment))
        ->assertSuccessful()
        ->assertSee($general->title)
        ->assertSee($ownCareer->title)
        ->assertDontSee($otherCareer->title)
        ->assertDontSee($otherCycle->title);
});

test('accepted students can download an in-scope material through a signed link', function () {
    Storage::fake('public');

    $preEnrollment = acceptedPreEnrollment();
    $material = PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $preEnrollment->cycle_id,
        'career_id' => $preEnrollment->career_id,
        'file_path' => 'pre_enrollment_materials/normas.pdf',
        'original_name' => 'normas.pdf',
    ]);
    Storage::disk('public')->put($material->file_path, 'contenido');

    $url = URL::temporarySignedRoute('preinsc.materials.download', now()->addDay(), [
        'preEnrollment' => $preEnrollment->getKey(),
        'material' => $material->getKey(),
    ]);

    $this->get($url)
        ->assertSuccessful()
        ->assertDownload('normas.pdf');
});

test('out of scope materials cannot be downloaded even with a signed link', function () {
    Storage::fake('public');

    $preEnrollment = acceptedPreEnrollment();
    $foreign = PreEnrollmentMaterial::factory()->create([
        'cycle_id' => $preEnrollment->cycle_id + 1,
        'file_path' => 'pre_enrollment_materials/otro.pdf',
    ]);
    Storage::disk('public')->put($foreign->file_path, 'contenido');

    $url = URL::temporarySignedRoute('preinsc.materials.download', now()->addDay(), [
        'preEnrollment' => $preEnrollment->getKey(),
        'material' => $foreign->getKey(),
    ]);

    $this->get($url)->assertNotFound();
});

test('the download link requires a valid signature', function () {
    $preEnrollment = acceptedPreEnrollment();
    $material = PreEnrollmentMaterial::factory()->create(['cycle_id' => $preEnrollment->cycle_id]);

    $this->get("/preinsc/materiales/{$preEnrollment->getKey()}/descargar/{$material->getKey()}")
        ->assertForbidden();
});
