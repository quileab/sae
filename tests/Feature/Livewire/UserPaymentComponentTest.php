<?php

use App\Livewire\UserPaymentComponent;
use App\Models\PlansDetail;
use App\Models\PlansMaster;
use App\Models\User;
use App\Models\UserPayments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->student = User::factory()->create(['role' => 'student']);
});

it('updates existing installments when combinePlans is true', function () {
    $this->actingAs($this->admin);

    // Create existing payment for the student
    $date = now()->startOfDay();
    UserPayments::create([
        'user_id' => $this->student->id,
        'title' => 'Old Title',
        'amount' => 1000,
        'paid' => 500,
        'date' => $date,
    ]);

    // Create a new plan with the same date
    $master = PlansMaster::create(['title' => 'New Plan']);
    PlansDetail::create([
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
    $installments = UserPayments::where('user_id', $this->student->id)->whereDate('date', $date)->get();

    expect($installments)->toHaveCount(1);
    expect($installments->first()->title)->toBe('New Updated Title');
    expect($installments->first()->amount)->toBe('2000.00');
    expect($installments->first()->paid)->toBe('500.00'); // Preserves paid amount
});

it('does not alter the amount of fully paid installments when combinePlans is true', function () {
    $this->actingAs($this->admin);

    $date = now()->startOfDay();
    UserPayments::create([
        'user_id' => $this->student->id,
        'title' => 'Fully Paid Cuota',
        'amount' => 1500,
        'paid' => 1500,
        'date' => $date,
    ]);

    $master = PlansMaster::create(['title' => 'New Plan']);
    PlansDetail::create([
        'plans_master_id' => $master->id,
        'title' => 'New Plan Cuota',
        'amount' => 3000, // Higher amount
        'date' => $date,
    ]);

    Livewire::test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->set('selectedPlan', $master->id)
        ->set('combinePlans', true)
        ->call('assignPayPlan');

    $installment = UserPayments::where('user_id', $this->student->id)->whereDate('date', $date)->first();

    expect($installment->amount)->toBe('1500.00'); // Remains unchanged
    expect($installment->title)->toBe('New Plan Cuota'); // Title can still be updated
});

it('duplicates installments when combinePlans is false', function () {
    $this->actingAs($this->admin);

    $date = now()->startOfDay();
    UserPayments::create([
        'user_id' => $this->student->id,
        'title' => 'Old Title',
        'amount' => 1000,
        'paid' => 0,
        'date' => $date,
    ]);

    $master = PlansMaster::create(['title' => 'New Plan']);
    PlansDetail::create([
        'plans_master_id' => $master->id,
        'title' => 'New Title',
        'amount' => 2000,
        'date' => $date,
    ]);

    Livewire::test(UserPaymentComponent::class, ['user' => $this->student->id])
        ->set('selectedPlan', $master->id)
        ->set('combinePlans', false)
        ->call('assignPayPlan');

    $installments = UserPayments::where('user_id', $this->student->id)->whereDate('date', $date)->get();

    expect($installments)->toHaveCount(2);
});
