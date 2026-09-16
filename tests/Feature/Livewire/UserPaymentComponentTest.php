<?php

use App\Livewire\UserPaymentComponent;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanDetail;
use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\UserPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->student = User::factory()->create(['role' => 'student']);
});

it('updates existing Installment when combinePlans is true', function () {
    $this->actingAs($this->admin);

    // Create existing payment for the student
    $date = now()->startOfDay();
    UserPayment::create([
        'user_id' => $this->student->id,
        'title' => 'Old Title',
        'amount' => 1000,
        'paid' => 500,
        'date' => $date,
    ]);

    // Create a new plan with the same date
    $master = PaymentPlan::create(['title' => 'New Plan']);
    PaymentPlanDetail::create([
        'plans_master_id' => $master->id,
        'title' => 'New Updated Title',
        'amount' => 2000,
        'date' => $date,
    ]);

    Livewire::test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->set('selectedPlan', $master->id)
        ->set('combinePlans', true)
        ->call('assignPayPlan');

    // Check that it was updated and NOT duplicated
    $Installment = UserPayment::where('user_id', $this->student->id)->whereDate('date', $date)->get();

    expect($Installment)->toHaveCount(1);
    expect($Installment->first()->title)->toBe('New Updated Title');
    expect($Installment->first()->amount)->toBe('2000.00');
    expect($Installment->first()->paid)->toBe('500.00'); // Preserves paid amount
});

it('does not alter the amount of fully paid Installment when combinePlans is true', function () {
    $this->actingAs($this->admin);

    $date = now()->startOfDay();
    UserPayment::create([
        'user_id' => $this->student->id,
        'title' => 'Fully Paid Cuota',
        'amount' => 1500,
        'paid' => 1500,
        'date' => $date,
    ]);

    $master = PaymentPlan::create(['title' => 'New Plan']);
    PaymentPlanDetail::create([
        'plans_master_id' => $master->id,
        'title' => 'New Plan Cuota',
        'amount' => 3000, // Higher amount
        'date' => $date,
    ]);

    Livewire::test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->set('selectedPlan', $master->id)
        ->set('combinePlans', true)
        ->call('assignPayPlan');

    $installment = UserPayment::where('user_id', $this->student->id)->whereDate('date', $date)->first();

    expect($installment->amount)->toBe('1500.00'); // Remains unchanged
    expect($installment->title)->toBe('New Plan Cuota'); // Title can still be updated
});

it('duplicates Installment when combinePlans is false', function () {
    $this->actingAs($this->admin);

    $date = now()->startOfDay();
    UserPayment::create([
        'user_id' => $this->student->id,
        'title' => 'Old Title',
        'amount' => 1000,
        'paid' => 0,
        'date' => $date,
    ]);

    $master = PaymentPlan::create(['title' => 'New Plan']);
    PaymentPlanDetail::create([
        'plans_master_id' => $master->id,
        'title' => 'New Title',
        'amount' => 2000,
        'date' => $date,
    ]);

    Livewire::test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->set('selectedPlan', $master->id)
        ->set('combinePlans', false)
        ->call('assignPayPlan');

    $Installment = UserPayment::where('user_id', $this->student->id)->whereDate('date', $date)->get();

    expect($Installment)->toHaveCount(2);
});

it('prevents a student from managing another user payments (IDOR)', function () {
    $otherStudent = User::factory()->create(['role' => 'student']);
    $otherPayment = UserPayment::create([
        'user_id' => $otherStudent->id,
        'title' => 'Otra Cuota',
        'amount' => 1000,
        'paid' => 0,
        'date' => now()->startOfDay(),
    ]);

    Livewire::actingAs($this->student)
        ->test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->call('handleInstallmentClick', $otherPayment->id)
        ->assertStatus(403);
});

it('prevents a student from modifying owed amounts (staff only)', function () {
    $payment = UserPayment::create([
        'user_id' => $this->student->id,
        'title' => 'Mi Cuota',
        'amount' => 1000,
        'paid' => 0,
        'date' => now()->startOfDay(),
    ]);

    Livewire::actingAs($this->student)
        ->test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->call('modifyAmount', $payment->id)
        ->assertStatus(403);
});

it('allows a student to register a payment on their own installment', function () {
    $payment = UserPayment::create([
        'user_id' => $this->student->id,
        'title' => 'Mi Cuota',
        'amount' => 1000,
        'paid' => 0,
        'date' => now()->startOfDay(),
    ]);

    Livewire::actingAs($this->student)
        ->test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->call('handleInstallmentClick', $payment->id)
        ->set('paymentAmountInput', 500)
        ->call('registerUserPayment')
        ->assertHasNoErrors();

    expect(PaymentRecord::where('user_id', $this->student->id)->exists())->toBeTrue();
});
