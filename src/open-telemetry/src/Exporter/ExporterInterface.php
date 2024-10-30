<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Exporter;

interface ExporterInterface
{
    public function configure(): void;
}
