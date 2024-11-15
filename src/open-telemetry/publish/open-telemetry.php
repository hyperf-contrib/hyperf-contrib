<?php

declare(strict_types=1);

use function Hyperf\Support\env;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
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
            'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),
        ],
    ],

    // The OpenTelemetry SDK will use this sampler to sample the spans.
    'sampler' => new AlwaysOnSampler(),

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

        // The OpenTelemetry SDK will enable the instrumentation features.
        'features' => [
            'client_request' => ['enabled' => env('OTEL_INSTRUMENTATION_FEATURES_CLIENT_REQUEST', true), 'options' => [
                // headers whitelist, supports wildcards，e.g. ['x-custom-*']
                'headers' => [
                    'request'  => ['*'],
                    'response' => ['*'],
                ],
            ]],
            'db_query' => ['enabled' => env('OTEL_INSTRUMENTATION_FEATURES_DB_QUERY', true), 'options' => [
                // combine the sql and bindings
                'combine_sql_and_bindings' => false,
            ]],
            'command' => ['enabled' => env('OTEL_INSTRUMENTATION_FEATURES_COMMAND', true), 'options' => []],
            'crontab' => ['enabled' => env('OTEL_INSTRUMENTATION_FEATURES_CRONTAB', true), 'options' => []],
        ],
    ],
];
