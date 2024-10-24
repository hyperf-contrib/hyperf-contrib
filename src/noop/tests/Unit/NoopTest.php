<?php

declare(strict_types=1);

use HyperfContrib\Noop\Noop;

it('should pass', function () {
    expect(true)->toBeTrue();
});

it('noop', function () {
    expect(Noop::noop())->toBe('noop');
});
