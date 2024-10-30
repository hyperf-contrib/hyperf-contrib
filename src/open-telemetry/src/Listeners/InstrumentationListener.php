<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listeners;

use HyperfContrib\OpenTelemetry\Switcher;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

abstract class InstrumentationListener
{
    public function __construct(
        protected readonly CachedInstrumentation $instrumentation,
        protected readonly Switcher $switcher,
    ) {
    }
}
