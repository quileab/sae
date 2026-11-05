<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.landing.api_key' => 'testing-landing-key']);
    $this->withHeaders(['X-Api-Key' => 'testing-landing-key']);
});

test('reports the pre-enrollment form as open by default', function () {
    $this->getJson('/api/public/pre-enrollments/form-status')
        ->assertSuccessful()
        ->assertJson(['open' => true])
        ->assertJsonStructure(['open', 'cycle']);
});

test('reports the pre-enrollment form as closed when the config is off', function () {
    DB::table('configs')->where('id', 'pre_enrollment_open')->update(['value' => 'false']);
    cache()->forget('config.pre_enrollment_open');

    $this->getJson('/api/public/pre-enrollments/form-status')
        ->assertSuccessful()
        ->assertJson(['open' => false]);
});

test('rejects requests without the api key', function () {
    $this->flushHeaders()
        ->getJson('/api/public/pre-enrollments/form-status')
        ->assertUnauthorized();
});

test('rejects requests with a wrong api key', function () {
    $this->withHeaders(['X-Api-Key' => 'wrong-key'])
        ->getJson('/api/public/pre-enrollments/form-status')
        ->assertUnauthorized();
});

test('allows repeated status checks from the same client', function () {
    foreach (range(1, 6) as $ignored) {
        $this->getJson('/api/public/pre-enrollments/form-status')->assertSuccessful();
    }
});
