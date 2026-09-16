<?php

use App\Livewire\PayPlans;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;

uses(DatabaseTransactions::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('renders the component', function () {
    $this->actingAs($this->admin);

    Livewire::test(PayPlans::class)
        ->assertSuccessful();
});

it('updates all installment amounts when defaultAmount is changed', function () {
    $this->actingAs($this->admin);

    Livewire::test(PayPlans::class)
        ->call('openCreateMasterForm')
        ->set('defaultAmount', 1500.50)
        ->assertSet('planDetails.0.amount', 1500.50)
        ->assertSet('planDetails.11.amount', 1500.50);
});

it('can create a new payment plan with Installment', function () {
    $this->actingAs($this->admin);

    Livewire::test(PayPlans::class)
        ->call('openCreateMasterForm')
        ->set('masterTitle', 'Plan Test 2026')
        ->set('defaultAmount', 2000)
        ->call('createMasterData');

    $master = PaymentPlan::where('title', 'Plan Test 2026')->first();
    expect($master)->not->toBeNull();
    expect(PaymentPlanDetail::where('plans_master_id', $master->id)->count())->toBe(12);
    expect(PaymentPlanDetail::where('plans_master_id', $master->id)->first()->amount)->toBe('2000.00');
});

it('can delete a payment plan', function () {
    $this->actingAs($this->admin);
    $plan = PaymentPlan::create(['title' => 'To be deleted']);

    Livewire::test(PayPlans::class)
        ->call('deleteMasterData', $plan->id);

    expect(PaymentPlan::find($plan->id))->toBeNull();
});
