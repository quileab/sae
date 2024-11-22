<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
  return view('dashboard');
});

// Users will be redirected to this route if not logged in
Volt::route('/login', 'login')->name('login');
Volt::route('/register', 'register'); 

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
        $logs[$key]='✔️';
      } catch (\Exception $e) {
        $logs[$key]='❌'.$e->getMessage();
      }
    }
    return "<pre>".print_r($logs,true)."</pre><hr />";
  });
  
  Volt::route('/dashboard', 'dashboard');
  Volt::route('/users', 'users.index');
  Volt::route('/careers', 'careers.index');