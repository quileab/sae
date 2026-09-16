<?php

use App\Models\PaymentRecord;
use App\Models\User;
use App\Models\UserPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use MercadoPago\Client\Payment\PaymentClient;

uses(RefreshDatabase::class);

test('mercadopago webhook records payment with valid signature', function () {
    Config::set('mercadopago.webhook_secret', 'test_secret');

    $user = User::factory()->create();
    $userPayment = UserPayment::create([
        'user_id' => $user->id,
        'date' => now(),
        'title' => 'Test Payment',
        'amount' => 100,
        'paid' => 0,
    ]);

    $paymentId = '999999';
    $requestId = 'req-123';
    $ts = time();
    $manifest = "id:$paymentId;request-id:$requestId;ts:$ts;";
    $signature = hash_hmac('sha256', $manifest, 'test_secret');
    $xSignature = "ts=$ts,v1=$signature";

    $mockPayment = (object) [
        'id' => $paymentId,
        'status' => 'approved',
        'transaction_amount' => 50,
        'external_reference' => $userPayment->id,
    ];

    $mockClient = Mockery::mock();
    $mockClient->shouldReceive('get')->with($paymentId)->andReturn($mockPayment);
    $this->app->instance(PaymentClient::class, $mockClient);

    $response = $this->withHeaders([
        'x-signature' => $xSignature,
        'x-request-id' => $requestId,
    ])->postJson("/mercadopago/webhook?id=$paymentId", [
        'type' => 'payment',
        'data' => ['id' => $paymentId],
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('userpayments', [
        'id' => $userPayment->id,
        'paid' => 50,
    ]);

    $this->assertDatabaseHas('paymentrecords', [
        'userpayments_id' => $userPayment->id,
        'transaction_id' => $paymentId,
        'paymentAmount' => 50,
    ]);
});

test('mercadopago webhook rejects invalid signature', function () {
    Config::set('mercadopago.webhook_secret', 'test_secret');

    $response = $this->withHeaders([
        'x-signature' => 'invalid',
        'x-request-id' => 'req-123',
    ])->postJson('/mercadopago/webhook?id=123', [
        'type' => 'payment',
        'data' => ['id' => '123'],
    ]);

    $response->assertForbidden();
});

test('mercadopago webhook ensures idempotency', function () {
    Config::set('mercadopago.webhook_secret', 'test_secret');

    $user = User::factory()->create();
    $userPayment = UserPayment::create([
        'user_id' => $user->id,
        'date' => now(),
        'title' => 'Test Payment',
        'amount' => 100,
        'paid' => 0,
    ]);

    $paymentId = '999999';
    $requestId = 'req-123';
    $ts = time();
    $manifest = "id:$paymentId;request-id:$requestId;ts:$ts;";
    $signature = hash_hmac('sha256', $manifest, 'test_secret');
    $xSignature = "ts=$ts,v1=$signature";

    $mockPayment = (object) [
        'id' => $paymentId,
        'status' => 'approved',
        'transaction_amount' => 50,
        'external_reference' => $userPayment->id,
    ];

    $mockClient = Mockery::mock();
    $mockClient->shouldReceive('get')->with($paymentId)->andReturn($mockPayment);
    $this->app->instance(PaymentClient::class, $mockClient);

    // First attempt
    $this->withHeaders([
        'x-signature' => $xSignature,
        'x-request-id' => $requestId,
    ])->postJson("/mercadopago/webhook?id=$paymentId", [
        'type' => 'payment',
        'data' => ['id' => $paymentId],
    ])->assertSuccessful();

    // Second attempt
    $this->withHeaders([
        'x-signature' => $xSignature,
        'x-request-id' => $requestId,
    ])->postJson("/mercadopago/webhook?id=$paymentId", [
        'type' => 'payment',
        'data' => ['id' => $paymentId],
    ])->assertSuccessful();

    // Verify only one record and one payment amount update
    $this->assertEquals(50, UserPayment::find($userPayment->id)->paid);
    $this->assertEquals(1, PaymentRecord::where('transaction_id', $paymentId)->count());
});
