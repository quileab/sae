<?php

use Illuminate\Pagination\LengthAwarePaginator;

test('pagination uses theme aware colors instead of forced light colors', function () {
    $paginator = new LengthAwarePaginator(range(1, 10), total: 50, perPage: 10, currentPage: 2, options: [
        'path' => '/users',
    ]);

    $html = (string) $paginator->links('livewire::sae');

    expect($html)
        ->toContain('bg-base-100')
        ->toContain('text-base-content')
        ->toContain('text-primary-content')
        ->not->toContain('bg-white')
        ->not->toContain('text-gray-');
});

test('livewire pagination theme is configured to the sae theme', function () {
    expect(config('livewire.pagination_theme'))->toBe('sae');
});
