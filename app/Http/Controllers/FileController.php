<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    /**
     * Serve private inscription PDFs with authorization checks.
     */
    public function serveInscriptionPdf(string $file): BinaryFileResponse
    {
        // Security: Prevent path traversal
        if (str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
            abort(403, 'Invalid file path');
        }

        // Ownership/Authorization check
        $user = auth()->user();
        $isAdmin = $user->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor']);

        if (! $isAdmin) {
            // Filename format: insc-$student->id-$career->id-$insc_conf_id-.pdf
            if (preg_match('/^insc-(\d+)-/', $file, $matches)) {
                $fileStudentId = (int) $matches[1];
                if ($user->id !== $fileStudentId) {
                    abort(403, 'Acceso denegado: No tienes permiso para ver este archivo.');
                }
            } else {
                abort(403, 'Acceso denegado: Formato de archivo no reconocido.');
            }
        }

        $path = storage_path('app/private/private/inscriptions/'.$file);
        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    }
}
