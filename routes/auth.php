<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

// Auth Routes
Route::livewire('/login', 'login')->name('login');

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    // Private File Serving
    Route::get('inscriptions/pdf/{file}', [FileController::class, 'serveInscriptionPdf'])->name('inscriptions.pdf');
});
