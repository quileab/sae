<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\print\PrintInscriptionsController;


// Users will be redirected to this route if not logged in
Volt::route('/login', 'login')->name('login');
// Volt::route('/register', 'register'); 
Route::get('/', function () {
  return redirect('/dashboard'); //view('dashboard');
});

Volt::route('/messages', 'messages.index')->middleware('auth');

Route::get('/clear/{option?}', function ($option = null) {
  $logs = [];
  $maintenance = ($option == "cache") ? [
    'Flush' => 'cache:flush',
  ] : [
    'DebugBar' => 'debugbar:clear',
    'Storage Link' => 'storage:link',
    'Config' => 'config:clear',
    'Optimize Clear' => 'optimize:clear',
    //'Optimize' => 'optimize',
    'Route Clear' => 'route:clear',
    'Cache' => 'cache:clear',
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
  $output = '<table><tr><th>Task</th><th>Result</th></tr>';
  foreach ($logs as $key => $value) {
    $output .= '<tr><td>' . $key . '</td><td>' . $value . '</td></tr>';
  }
  $output .= '</table>';
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
});
