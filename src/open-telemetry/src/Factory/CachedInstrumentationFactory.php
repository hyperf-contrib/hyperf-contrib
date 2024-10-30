<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Factory;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SemConv\Version;

class CachedInstrumentationFactory
{
    public function __invoke(): CachedInstrumentation
    {
        return new CachedInstrumentation(
            name: 'hyperf-contrib/open-telemetry',
            schemaUrl: Version::VERSION_1_27_0->url(),
            attributes: [
                'instrumentation.name' => 'hyperf-contrib/open-telemetry',
            ],
        );
    }
}
