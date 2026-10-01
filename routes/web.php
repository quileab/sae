<?php

use App\Livewire\PreEnrollments\PublicForm;
use App\Models\Career;
use App\Models\PreEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public pre-enrollment (no auth)
Route::get('/preinsc', PublicForm::class)->name('preinsc');
Volt::route('/preinsc/secundario', 'pre-enrollments.secondary-form')->name('preinsc.secundario');
Route::get('/preinsc/pdf/{preEnrollment}', function (PreEnrollment $preEnrollment) {
    abort_unless($preEnrollment->pdf_path && file_exists(storage_path('app/'.$preEnrollment->pdf_path)), 404);

    return response()->download(storage_path('app/'.$preEnrollment->pdf_path), 'preinscripcion-'.$preEnrollment->id.'.pdf');
})->middleware(['signed', 'throttle:10,1'])->name('preinsc.pdf');
Route::post('/api/public/pre-enrollments', function (Request $request) {
    $validated = $request->validate([
        'cycle_id' => 'required|integer|min:2025|max:2030',
        'career_id' => 'required|exists:careers,id',
        'doc_type' => 'required|string|max:10',
        'doc_number' => 'required|string|max:20',
        'email' => 'nullable|email',
        'phone' => 'nullable|string|max:50',
        'payload' => 'required|array',
    ]);

    // Quota check
    $career = Career::find($validated['career_id']);
    if ($career && $career->enrollment_quota_enabled && $career->enrollment_quota_2027) {
        $count = PreEnrollment::where('cycle_id', $validated['cycle_id'])
            ->where('career_id', $validated['career_id'])
            ->whereIn('status', ['validated', 'enrolled'])->count();
        if ($count >= $career->enrollment_quota_2027) {
            $validated['status'] = 'waitlisted';
        }
    }

    $pre = PreEnrollment::create([
        'cycle_id' => $validated['cycle_id'],
        'career_id' => $validated['career_id'],
        'doc_type' => $validated['doc_type'],
        'doc_number' => $validated['doc_number'],
        'email' => $validated['email'] ?? null,
        'phone' => $validated['phone'] ?? null,
        'payload' => $validated['payload'],
        'status' => $validated['status'] ?? 'submitted',
    ]);

    return response()->json($pre, 201);
})->middleware('throttle:30,1');

// Redirect root to dashboard
Route::redirect('/', '/dashboard');

// Load Modular Routes
require __DIR__.'/auth.php';
require __DIR__.'/academic.php';
require __DIR__.'/payments.php';
