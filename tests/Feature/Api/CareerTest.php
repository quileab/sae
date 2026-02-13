<?php

use App\Models\Career;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns only careers that allow enrollments', function () {
    // Create careers that allow enrollments
    $allowedCareers = Career::factory()->count(3)->create(['allow_enrollments' => true]);

    // Create careers that do not allow enrollments
    Career::factory()->count(2)->create(['allow_enrollments' => false]);

    $response = $this->getJson('/api/careers');

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'resolution',
                    'allow_enrollments',
                    'allow_evaluations',
                    'created_at',
                    'updated_at',
                ],
            ],
        ]);

    foreach ($allowedCareers as $career) {
        $response->assertJsonFragment(['id' => $career->id]);
    }
});
