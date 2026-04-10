<?php

use App\Http\Controllers\Api\AttendanceSyncController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\print\PrintClassbookController;
use App\Http\Controllers\print\PrintInscriptionsController;
use App\Http\Controllers\print\PrintPaymentsController;
use App\Http\Controllers\print\PrintStudentReportController;
use App\Http\Controllers\ReceiptController;
use App\Livewire\Chat;
use App\Livewire\ContentManager;
use App\Livewire\Subjects\Content as SubjectsContent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Users will be redirected to this route if not logged in
Route::livewire('/login', 'login')->name('login');
Volt::route('/pago-online', 'public-payment')->name('public-payment');
// Route::livewire('/register', 'register');
Route::get('/', function () {
    return redirect('/dashboard'); // view('dashboard');
});

// Route::get('/clear/{option?}', function ($option = null) {
//   $logs = [];
//   $maintenance = ($option == "cache") ? [
//     'Flush' => 'cache:flush',
//   ] : [
//     //'DebugBar' => 'debugbar:clear',
//     'Storage Link' => 'storage:link',
//     'Config' => 'config:clear',
//     'Cache' => 'cache:clear',
//     'View' => 'view:clear',
//     'Optimize Clear' => 'optimize:clear',
//     //'Optimize' => 'optimize',
//     'Route Clear' => 'route:clear',
//   ];

//   foreach ($maintenance as $key => $value) {
//     try {
//       Artisan::call($value);
//       $logs[$key] = '✔️';
//     } catch (\Exception $e) {
//       $logs[$key] = '❌' . $e->getMessage();
//     }
//   }
//   // table format output
//   $output = '<html><body style="font-family:sans-serif; background-color:#303030; color:white;">
//   <table><tr><th>Task</th><th>Result</th></tr>';
//   foreach ($logs as $key => $value) {
//     $output .= '<tr><td>' . $key . '</td><td style="text-align:center;">' . $value . '</td></tr>';
//   }
//   $output .= '</table></body></html>';
//   return $output;
// });

Route::middleware('auth')->group(function () {

    Route::get('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    });

    Route::get('/inscriptionsPDF/{student}/{career}/{inscription}', [PrintInscriptionsController::class, 'index'])->name('inscriptionsPDF');
    Route::get('/inscriptionsSavePDF/{student}/{career}/{inscription}', [PrintInscriptionsController::class, 'savePDF'])->name('inscriptionsSavePDF');
    Route::get('/printClassbooks/{subject?}/{id?}', [PrintClassbookController::class, 'printClassbooks'])->name('printclassbooks')->middleware('roles:admin,teacher,principal,director,administrative');

    Route::prefix('bookmark')->group(function () {
        Route::get('/', [BookmarkController::class, 'index']);        // Ver el bookmark actual
        Route::post('/update', [BookmarkController::class, 'update']); // Actualizar un bookmark
        Route::post('/clear', [BookmarkController::class, 'clear']);  // Limpiar los bookmarks
    });

    // route for pdfs stored in private storage
    Route::get('inscriptions/pdf/{file}', function ($file) {
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
    });

    Route::livewire('/dashboard', 'dashboard');
    Route::livewire('/users', 'users.index')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/user/{id?}', 'users.crud')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/users/import', 'users.import')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/careers', 'careers.index')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/career/{id?}', 'careers.crud')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/subjects', 'subjects.index')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/subjects-table', 'subjects.table-crud')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/subject/{id?}', 'subjects.crud')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/enrollments', 'enrollment')->middleware('roles:admin,student,principal,director,administrative');
    Route::livewire('/configs', 'configs')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/inscriptions', 'inscriptions.crud')->middleware('roles:admin,student,principal,director,administrative');
    Route::livewire('/inscriptions/list', 'inscriptions.index')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::livewire('/inscriptions/pdfs', 'inscriptions.indexpdfs')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/class-sessions', 'class_sessions.index')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::livewire('/class-session/{id?}', 'class_sessions.crud')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::livewire('/class-sessions/students/{id?}', 'class_sessions.students')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/chat', Chat::class)->middleware('auth');
    Route::get('/print/student-report/{subject_id}', [PrintStudentReportController::class, 'generateReport'])->name('print.student-report')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/print/student-attendance-report/{subject_id}', [PrintStudentReportController::class, 'generateAttendanceReport'])->name('print.student-attendance-report')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/print/student-grades-report/{subject_id}', [PrintStudentReportController::class, 'generateGradesReport'])->name('print.student-grades-report')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/print/students-payments', [PrintStudentReportController::class, 'printStudentsPayments'])->name('printStudentsPayments')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/subjects/{subject}/content', SubjectsContent::class)->name('subjects.content')->middleware('roles:admin,teacher,principal,director');
    Route::get('/subjects/{subject}/content-manager', ContentManager::class)->name('subjects.content-manager')->middleware('roles:admin,teacher');
    Route::livewire('/simplified-content/{subject}', 'simplified-content')->name('simplified-content')->middleware('roles:admin,teacher,director,student,principal');
    Route::livewire('/calendar', 'calendar')->name('calendar')->middleware('roles:admin,teacher,principal,director,administrative,student');
    Route::livewire('/attendance', 'attendance.index')->name('attendance')->middleware('roles:preceptor,admin,principal,director,administrative');
    Route::livewire('/profile', 'users.profile')->name('profile');

    // Internal Routes for Attendance PWA (Full session support)
    Route::prefix('pwa-attendance')->group(function () {
        Route::get('/students', [AttendanceSyncController::class, 'students']);
        Route::post('/sync', [AttendanceSyncController::class, 'store']);
    });

    // Payment System Routes
    Route::livewire('/pay-plans', 'pay-plans')->middleware('roles:admin,principal,director,administrative');
    Route::get('/user-payments-index', function () {
        return redirect()->route('user-payments');
    })->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/user-payments/{user?}', 'user-payment-component')->name('user-payments')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/payments-details/{user}', 'payments-details')->name('payments-details')->middleware('roles:admin,principal,director,administrative,student');
    Route::livewire('/report-payments', 'report-payments')->name('report-payments')->middleware('roles:admin,principal,director,administrative');
    Volt::route('/report-debts', 'report-debts-sfc')->name('report-debts')->middleware('roles:admin,principal,director,administrative');

    Route::livewire('/my-payment-plan', 'user-payment-component')->name('my-payment-plan')->middleware('roles:student');

    Route::get('/payments/receipt/{paymentRecord}', [ReceiptController::class, 'show'])->name('payments.receipt');
    Route::get('/payments/summary/{user}', [PrintPaymentsController::class, 'summary'])->name('user-payments.summary');

    // Biblioteca (Libros y Préstamos)
    Route::livewire('/books', 'books.index')->name('books.index')->middleware('roles:admin,administrative,director,preceptor,student,teacher');
    Route::livewire('/books/print', 'books.print')->name('books.print')->middleware('roles:admin,administrative,director,preceptor,student,teacher');
    Route::livewire('/books/create', 'books.form')->name('books.create')->middleware('roles:admin,administrative,director,preceptor');
    Route::livewire('/books/{id}/edit', 'books.form')->name('books.edit')->middleware('roles:admin,administrative,director,preceptor');
    Route::livewire('/books/loans', 'books.loans')->name('books.loans')->middleware('roles:admin,administrative,director,preceptor');
});

// Mercado Pago Routes
Route::get('/mercadopago/success', [MercadoPagoController::class, 'success'])->name('mercadopago.success');
Route::get('/mercadopago/failure', [MercadoPagoController::class, 'failure'])->name('mercadopago.failure');
Route::get('/mercadopago/pending', [MercadoPagoController::class, 'pending'])->name('mercadopago.pending');
Route::post('/mercadopago/webhook', [MercadoPagoController::class, 'webhook'])->name('mercadopago.webhook');
