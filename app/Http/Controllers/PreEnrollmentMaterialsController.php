<?php

namespace App\Http\Controllers;

use App\Enums\PreEnrollmentStatus;
use App\Models\PreEnrollment;
use App\Models\PreEnrollmentMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PreEnrollmentMaterialsController extends Controller
{
    /**
     * Área pública de materiales para inscriptos aceptados (enlace firmado).
     */
    public function show(PreEnrollment $preEnrollment): Response
    {
        abort_unless($this->isAccepted($preEnrollment), 404);

        return response()
            ->view('pre-enrollments.materials-public', [
                'preEnrollment' => $preEnrollment->loadMissing('career'),
                'materials' => $this->materialsFor($preEnrollment),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Descarga de un material (enlace firmado por archivo).
     */
    public function download(PreEnrollment $preEnrollment, PreEnrollmentMaterial $material): StreamedResponse
    {
        abort_unless($this->isAccepted($preEnrollment), 404);
        abort_unless($this->materialsFor($preEnrollment)->contains('id', $material->getKey()), 404);
        abort_unless(Storage::disk('public')->exists($material->file_path), 404);

        return Storage::disk('public')->download($material->file_path, $material->original_name);
    }

    private function isAccepted(PreEnrollment $preEnrollment): bool
    {
        return in_array($preEnrollment->status, [
            PreEnrollmentStatus::Validated->value,
            PreEnrollmentStatus::Enrolled->value,
        ], true);
    }

    /**
     * Materiales del ciclo de la preinscripción, generales o de su carrera.
     *
     * @return Collection<int, PreEnrollmentMaterial>
     */
    private function materialsFor(PreEnrollment $preEnrollment): Collection
    {
        return PreEnrollmentMaterial::forPreEnrollment($preEnrollment);
    }
}
