<?php

declare(strict_types=1);

use HyperfContrib\Noop\Noop;

it('should pass', function () {
    $this->assertTrue(true);
});

it('noop', function () {
    $this->assertSame('noop', Noop::noop());
});
