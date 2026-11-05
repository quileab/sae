<?php

namespace App\Http\Controllers\Api;

use App\Enums\PreEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\PreEnrollment;
use App\Models\PreEnrollmentMaterial;
use App\Services\AcademicCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PreEnrollmentStatusController extends Controller
{
    /**
     * Consulta pública del estado de una preinscripción (DNI + fecha de nacimiento).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $rawDni = $request->input('dni');
        $dni = is_string($rawDni) || is_numeric($rawDni)
            ? preg_replace('/\D+/', '', (string) $rawDni)
            : '';

        $validator = Validator::make(
            ['dni' => $dni, 'birthdate' => $request->input('birthdate')],
            [
                'dni' => ['required', 'string', 'digits_between:6,10'],
                'birthdate' => ['required', 'date'],
            ]
        );

        if ($validator->fails()) {
            return response()
                ->json(['message' => 'Datos inválidos.', 'errors' => $validator->errors()], 422)
                ->withHeaders($this->headers());
        }

        $birthdate = Carbon::parse((string) $request->input('birthdate'))->startOfDay();

        $preEnrollment = PreEnrollment::query()
            ->with('career')
            ->where('doc_number', $dni)
            ->where('cycle_id', AcademicCycle::enrollmentCycle())
            ->latest()
            ->first();

        if (! $preEnrollment instanceof PreEnrollment || ! $this->birthdateMatches($preEnrollment, $birthdate)) {
            return response()->json(['found' => false])->withHeaders($this->headers());
        }

        return $this->foundResponse($preEnrollment);
    }

    /**
     * La fecha de nacimiento se guarda dentro del payload JSON, por lo que la
     * comparación se hace en PHP y no en SQL.
     */
    private function birthdateMatches(PreEnrollment $preEnrollment, Carbon $birthdate): bool
    {
        $stored = $preEnrollment->payload['fnacimiento'] ?? null;

        if (! is_string($stored) || trim($stored) === '') {
            return false;
        }

        try {
            return Carbon::parse($stored)->startOfDay()->isSameDay($birthdate);
        } catch (Throwable) {
            return false;
        }
    }

    private function foundResponse(PreEnrollment $preEnrollment): JsonResponse
    {
        $status = PreEnrollmentStatus::tryFrom($preEnrollment->status);
        $group = $status ? $this->group($status) : 'in_process';

        return response()->json([
            'found' => true,
            'group' => $group,
            'status' => $preEnrollment->status,
            'label' => $status?->label() ?? 'En proceso',
            'student_name' => $preEnrollment->full_name,
            'career' => $preEnrollment->career?->name,
            'cycle' => $preEnrollment->cycle_id,
            'updated_at' => ($preEnrollment->reviewed_at ?? $preEnrollment->updated_at)?->toIso8601String(),
            'materials_url' => $group === 'accepted' ? $this->materialsUrl($preEnrollment) : null,
            'materials' => $group === 'accepted' ? $this->materialsList($preEnrollment) : [],
        ])->withHeaders($this->headers());
    }

    /**
     * Listado de materiales disponibles para el alumno aceptado, cada uno con
     * su enlace firmado y temporal de descarga.
     *
     * @return array<int, array{title: string, original_name: string, download_url: string}>
     */
    private function materialsList(PreEnrollment $preEnrollment): array
    {
        return PreEnrollmentMaterial::forPreEnrollment($preEnrollment)
            ->map(fn (PreEnrollmentMaterial $material) => [
                'title' => $material->title,
                'original_name' => $material->original_name,
                'download_url' => URL::temporarySignedRoute(
                    'preinsc.materials.download',
                    now()->addDays(2),
                    ['preEnrollment' => $preEnrollment->getKey(), 'material' => $material->getKey()]
                ),
            ])
            ->all();
    }

    /**
     * Enlace firmado y temporal al área pública de materiales para inscriptos.
     */
    private function materialsUrl(PreEnrollment $preEnrollment): string
    {
        return URL::temporarySignedRoute(
            'preinsc.materials',
            now()->addDays(2),
            ['preEnrollment' => $preEnrollment->getKey()]
        );
    }

    private function group(PreEnrollmentStatus $status): string
    {
        return match ($status) {
            PreEnrollmentStatus::Validated,
            PreEnrollmentStatus::Enrolled => 'accepted',
            PreEnrollmentStatus::Waitlisted => 'waitlist',
            PreEnrollmentStatus::Rejected => 'rejected',
            default => 'in_process',
        };
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}
