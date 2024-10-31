<?php

declare(strict_types=1);

use function Hyperf\Support\env;
use OpenTelemetry\SemConv\ResourceAttributes;

return [
    // The OpenTelemetry SDK will use this service resource to identify the service.
    'resource' => [
        ResourceAttributes::SERVICE_NAMESPACE           => env('APP_NAMESPACE', 'hyperf-contrib'),
        ResourceAttributes::SERVICE_NAME                => env('APP_NAME', 'hyperf-app'),
        ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => env('APP_ENV', 'production'),
    ],

    // The OpenTelemetry SDK will use this URL to send the spans to the collector.
    'exporter' => [
        'otlp' => [
            'endpoint' => 'http://localhost:4318',
        ],
    ],

    // The OpenTelemetry SDK will use this instrumentation to listen to the events.
    'instrumentation' => [
        // The OpenTelemetry SDK will enable the instrumentation.
        'enabled' => env('OTEL_INSTRUMENTATION_ENABLED', true),

        // The OpenTelemetry SDK will enable the instrumentation tracing.
        'tracing' => env('OTEL_INSTRUMENTATION_TRACING_ENABLED', true),

        // The OpenTelemetry SDK will enable the instrumentation meter.
        'meter' => env('OTEL_INSTRUMENTATION_METER_ENABLED', true),

        // The OpenTelemetry SDK will enable the instrumentation logger.
        'logger' => env('OTEL_INSTRUMENTATION_LOGGER_ENABLED', true),

        // The OpenTelemetry SDK will enable the instrumentation listener.
        'listeners' => [
            'client_request' => ['enabled' => env('OTEL_INSTRUMENTATION_LISTENERS_CLIENT_REQUEST', true), 'options' => []],
            'db_query'       => ['enabled' => env('OTEL_INSTRUMENTATION_LISTENERS_DB_QUERY', true), 'options' => []],
            'command'        => ['enabled' => env('OTEL_INSTRUMENTATION_LISTENERS_COMMAND', true), 'options' => []],
            'crontab'        => ['enabled' => env('OTEL_INSTRUMENTATION_LISTENERS_CRONTAB', true), 'options' => []],
        ],
    ],
];
