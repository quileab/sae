<?php

use App\Livewire\PayPlans;
use App\Livewire\ReportPayments;
use App\Livewire\UserPaymentComponent;
use App\Models\PlansDetail;
use App\Models\PlansMaster;
use App\Models\User;
use App\Models\UserPayments;
use Carbon\Carbon;
use Livewire\Livewire;

test('it displays dates in dd/mm/aaaa format in UserPaymentComponent', function () {
    $user = User::factory()->create(['role' => 'student']);
    $payment = UserPayments::create([
        'user_id' => $user->id,
        'amount' => 1000,
        'paid' => 0,
        'date' => Carbon::create(2026, 2, 7),
        'title' => 'Cuota Test',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Livewire::test(UserPaymentComponent::class, ['user' => $user->id])
        ->assertSee('07/02/2026');
});

test('it displays dates in dd/mm/aaaa format in PayPlans', function () {
    $master = PlansMaster::create(['title' => 'Plan Test']);
    PlansDetail::create([
        'plans_master_id' => $master->id,
        'amount' => 1000,
        'date' => Carbon::create(2026, 3, 15),
        'title' => 'Cuota Test',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Livewire::test(PayPlans::class)
        ->assertSee('15/03/2026');
});

test('it uses Y-m-d format for date inputs in ReportPayments', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));

    Livewire::test(ReportPayments::class)
        ->assertSet('dateTo', now()->format('Y-m-d'));
});
