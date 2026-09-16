<?php

use Illuminate\Support\Facades\Route;

// Redirect root to dashboard
Route::redirect('/', '/dashboard');

// Load Modular Routes
require __DIR__.'/auth.php';
require __DIR__.'/academic.php';
require __DIR__.'/payments.php';
