<?php

declare(strict_types=1);

use HyperfContrib\Noop\Noop;

it('should pass', function () {
    $this->assertTrue(true);
});

it('noop', function () {
    expect(fn () => Noop::noop())->not()->toThrow(Exception::class);
});
