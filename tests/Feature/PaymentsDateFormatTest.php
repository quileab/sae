<?php

use App\Livewire\PayPlans;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('it displays dates in dd/mm/aaaa format in PayPlans', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    $master = PaymentPlan::create(['title' => 'Plan Test']);
    PaymentPlanDetail::create([
        'plans_master_id' => $master->id,
        'amount' => 1000,
        'date' => Carbon::create(2026, 3, 15),
        'title' => 'Cuota 1',
    ]);

    Livewire::test(PayPlans::class)
        ->assertSee('15/03/2026');
});
