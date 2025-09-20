<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\print\PrintClassbookController;
use App\Http\Controllers\print\PrintInscriptionsController;
use App\Http\Controllers\print\PrintStudentReportController;
use App\Livewire\Chat;
use App\Livewire\Subjects\Content as SubjectsContent;

// Users will be redirected to this route if not logged in
Volt::route('/login', 'login')->name('login');
// Volt::route('/register', 'register'); 
Route::get('/', function () {
  return redirect('/dashboard'); //view('dashboard');
});

Route::get('/clear/{option?}', function ($option = null) {
  $logs = [];
  $maintenance = ($option == "cache") ? [
    'Flush' => 'cache:flush',
  ] : [
    //'DebugBar' => 'debugbar:clear',
    'Storage Link' => 'storage:link',
    'Config' => 'config:clear',
    'Cache' => 'cache:clear',
    'View' => 'view:clear',
    'Optimize Clear' => 'optimize:clear',
    //'Optimize' => 'optimize',
    'Route Clear' => 'route:clear',
  ];

  foreach ($maintenance as $key => $value) {
    try {
      Artisan::call($value);
      $logs[$key] = '✔️';
    } catch (\Exception $e) {
      $logs[$key] = '❌' . $e->getMessage();
    }
  }
  // table format output
  $output = '<html><body style="font-family:sans-serif; background-color:#303030; color:white;">
  <table><tr><th>Task</th><th>Result</th></tr>';
  foreach ($logs as $key => $value) {
    $output .= '<tr><td>' . $key . '</td><td style="text-align:center;">' . $value . '</td></tr>';
  }
  $output .= '</table></body></html>';
  return $output;
});

Route::middleware('auth')->group(function () {

  Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
  });

  Route::get('/inscriptionsPDF/{student}/{career}/{inscription}', [PrintInscriptionsController::class, 'index'])->name('inscriptionsPDF');
  Route::get('/inscriptionsSavePDF/{student}/{career}/{inscription}', [PrintInscriptionsController::class, 'savePDF'])->name('inscriptionsSavePDF');
  Route::get('/printClassbooks/{subject?}/{id?}', [PrintClassbookController::class, 'printClassbooks'])->name('printclassbooks');

  Route::prefix('bookmark')->group(function () {
    Route::get('/', [BookmarkController::class, 'index']);        // Ver el bookmark actual
    Route::post('/update', [BookmarkController::class, 'update']); // Actualizar un bookmark
    Route::post('/clear', [BookmarkController::class, 'clear']);  // Limpiar los bookmarks
  });

  // route for pdfs stored in private storage
  Route::get('inscriptions/pdf/{file}', function ($file) {
    return response()->file(storage_path('app/private/private/inscriptions/' . $file));
  });

  Volt::route('/dashboard', 'dashboard');
  Volt::route('/users', 'users.index')->middleware('roles:admin,principal,administrative');
  Volt::route('/user/{id?}', 'users.crud')->middleware('roles:admin,principal,administrative');
  Volt::route('/users/import', 'users.import')->middleware('roles:admin,principal,administrative');
  Volt::route('/careers', 'careers.index')->middleware('roles:admin,principal,administrative');
  Volt::route('/career/{id?}', 'careers.crud')->middleware('roles:admin,principal,administrative');
  Volt::route('/subjects', 'subjects.index')->middleware('roles:admin,principal,administrative');
  Volt::route('/subject/{id?}', 'subjects.crud')->middleware('roles:admin,principal,administrative');
  Volt::route('/enrollments', 'enrollment')->middleware('roles:admin,student,principal,administrative');
  Volt::route('/configs', 'configs')->middleware('roles:admin,principal,administrative');
  Volt::route('/inscriptions', 'inscriptions.crud')->middleware('roles:admin,student,principal,administrative');
  Volt::route('/inscriptions/list', 'inscriptions.index')->middleware('roles:admin,teacher,principal,administrative');
  Volt::route('/inscriptions/pdfs', 'inscriptions.indexpdfs')->middleware('roles:admin,principal,administrative');
  Volt::route('/class-sessions', 'class_sessions.index')->middleware('roles:admin,teacher,principal,administrative');
  Volt::route('/class-session/{id?}', 'class_sessions.crud')->middleware('roles:admin,teacher,principal,administrative');
  Volt::route('/class-sessions/students/{id?}', 'class_sessions.students')->middleware('roles:admin,teacher,principal,administrative');
  Route::get('/chat', Chat::class)->middleware('auth');
  Route::get('/print/student-report/{subject_id}', [PrintStudentReportController::class, 'generateReport'])->name('print.student-report');
  Route::get('/print/student-attendance-report/{subject_id}', [\App\Http\Controllers\print\PrintStudentReportController::class, 'generateAttendanceReport'])->name('print.student-attendance-report');
  Volt::route('/subjects/{subject}/content', SubjectsContent::class)->name('subjects.content')->middleware('roles:admin,teacher,principal');
  Route::get('/subjects/{subject}/content-manager', \App\Livewire\ContentManager::class)->name('subjects.content-manager')->middleware('roles:admin,teacher');
  Volt::route('/simplified-content/{subject}', 'simplified-content')->name('simplified-content')->middleware('roles:admin,teacher,director,student,principal');
});
