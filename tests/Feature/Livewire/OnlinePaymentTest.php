<?php

use App\Livewire\OnlinePayment;
use App\Models\User;
use App\Models\UserPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->student = User::factory()->create(['role' => 'student']);
    $this->otherStudent = User::factory()->create(['role' => 'student']);
});

it('allows the owner to access their online payment', function () {
    $payment = UserPayment::create([
        'user_id' => $this->student->id,
        'title' => 'Cuota',
        'amount' => 1000,
        'paid' => 0,
        'date' => now()->startOfDay(),
    ]);

    Livewire::actingAs($this->student)
        ->test(OnlinePayment::class, ['userPaymentId' => $payment->id])
        ->assertOk();
});

it('denies a student access to another users payment', function () {
    $payment = UserPayment::create([
        'user_id' => $this->otherStudent->id,
        'title' => 'Cuota Ajena',
        'amount' => 1000,
        'paid' => 0,
        'date' => now()->startOfDay(),
    ]);

    Livewire::actingAs($this->student)
        ->test(OnlinePayment::class, ['userPaymentId' => $payment->id])
        ->assertStatus(403);
});
