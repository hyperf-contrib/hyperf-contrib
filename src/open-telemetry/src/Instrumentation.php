<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry;

use HyperfContrib\OpenTelemetry\Contract\InstrumentationInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

class Instrumentation implements InstrumentationInterface
{
    public function __construct(
        protected readonly CachedInstrumentation $instrumentation,
    ) {
    }

    public function tracer(): \OpenTelemetry\API\Trace\TracerInterface
    {
        return $this->instrumentation->tracer();
    }
}
