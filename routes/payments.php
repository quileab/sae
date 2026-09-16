<?php

use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\print\PrintPaymentsController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Volt::route('/pago-online', 'public-payment')->name('public-payment');

Route::middleware(['auth'])->group(function () {
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
});

// Mercado Pago Routes
Route::get('/mercadopago/success', [MercadoPagoController::class, 'success'])->name('mercadopago.success');
Route::get('/mercadopago/failure', [MercadoPagoController::class, 'failure'])->name('mercadopago.failure');
Route::get('/mercadopago/pending', [MercadoPagoController::class, 'pending'])->name('mercadopago.pending');
Route::post('/mercadopago/webhook', [MercadoPagoController::class, 'webhook'])->name('mercadopago.webhook');
