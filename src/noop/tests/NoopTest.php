<?php

declare(strict_types=1);

namespace HyperfContrib\Noop\Tests;

use HyperfContrib\Noop\Noop;
use PHPUnit\Framework\TestCase;

class NoopTest extends TestCase
{
    public function test_noop(): void
    {
        $this->assertSame('noop', Noop::noop());
    }
}
