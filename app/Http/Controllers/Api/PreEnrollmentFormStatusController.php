<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AcademicCycle;
use App\Services\PreEnrollmentSettings;
use Illuminate\Http\JsonResponse;

class PreEnrollmentFormStatusController extends Controller
{
    /**
     * Estado público del formulario de preinscripción, para que la landing
     * muestre u oculte los accesos al formulario según corresponda.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'open' => PreEnrollmentSettings::isOpen(),
            'cycle' => AcademicCycle::enrollmentCycle(),
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
