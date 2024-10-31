<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Contract;

interface ExporterInterface
{
    public function configure(): void;
}
