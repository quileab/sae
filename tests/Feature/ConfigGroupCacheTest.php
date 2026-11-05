<?php

use App\Models\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the config group cache is invalidated when a config changes', function () {
    $config = Config::create([
        'id' => 'insc_2024',
        'group' => 'inscriptions',
        'value' => 'true',
        'description' => 'Inscripción 2024',
        'type' => 'bool',
    ]);

    expect(Config::getGroup('inscriptions')->first()->value)->toBe('true');

    $config->update(['value' => 'false']);

    expect(Config::getGroup('inscriptions')->first()->value)->toBe('false');
});
