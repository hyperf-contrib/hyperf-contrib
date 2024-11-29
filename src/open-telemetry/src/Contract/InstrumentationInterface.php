<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Contract;

use OpenTelemetry\API\Trace\TracerInterface;

interface InstrumentationInterface
{
    public function tracer(): TracerInterface;
}
