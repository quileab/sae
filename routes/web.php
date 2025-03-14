<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\BookmarkController;

Route::get('/', function () {
  return view('dashboard');
});

// Users will be redirected to this route if not logged in
Volt::route('/login', 'login')->name('login');
// Volt::route('/register', 'register'); 

Route::middleware('auth')->group(function () {

  Route::get('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
  });

  Route::get('/clear/{option?}', function ($option = null) {
    $logs = [];
    $maintenance = ($option == "cache") ? [
      'Flush' => 'cache:flush',
    ] : [
      //'DebugBar'=>'debugbar:clear',
      //'Storage Link'=>'storage:link',
      'Config' => 'config:clear',
      'Optimize Clear' => 'optimize:clear',
      //'Optimize'=>'optimize',
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
    return "<pre>" . print_r($logs, true) . "</pre><hr />";
  });

  Route::prefix('bookmark')->group(function () {
    Route::get('/', [BookmarkController::class, 'index']);        // Ver el bookmark actual
    Route::post('/update', [BookmarkController::class, 'update']); // Actualizar un bookmark
    Route::post('/clear', [BookmarkController::class, 'clear']);  // Limpiar los bookmarks
  });

  Volt::route('/dashboard', 'dashboard');
  Volt::route('/users', 'users.index');
  Volt::route('/user/{id?}', 'users.crud');
  Volt::route('/careers', 'careers.index');
  Volt::route('/career/{id?}', 'careers.crud');
  Volt::route('/subjects', 'subjects.index');
  Volt::route('/subject/{id?}', 'subjects.crud');
  Volt::route('/enrollments', 'enrollment');
  Volt::route('/configs', 'configs');
  Volt::route('/configs/inscriptions', 'configs.inscriptions');
  Volt::route('/class-sessions', 'class_sessions.index');
  Volt::route('/class-session/{id?}', 'class_sessions.crud');
  Volt::route('/class-sessions/students/{id?}', 'class_sessions.students');
});
