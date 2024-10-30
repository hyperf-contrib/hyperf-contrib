<?php

declare(strict_types=1);

use OpenTelemetry\SemConv\ResourceAttributes;

return [
    // The OpenTelemetry SDK will use this service resource to identify the service.
    'resource' => [
        ResourceAttributes::SERVICE_NAMESPACE           => 'service_namespace',
        ResourceAttributes::SERVICE_NAME                => 'service_name',
        ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => 'deployment_environment_name',
    ],

    // The OpenTelemetry SDK will use this URL to send the spans to the collector.
    'exporter' => [
        'otlp' => [
            'endpoint' => 'http://localhost:4317',
        ],
    ],

    // The OpenTelemetry SDK will use this instrumentation to listen to the events.
    'instrumentation' => [
        // The OpenTelemetry SDK will enable the instrumentation.
        'enabled' => true,

        // The OpenTelemetry SDK will enable the instrumentation tracing.
        'tracing' => true,

        // The OpenTelemetry SDK will enable the instrumentation meter.
        'meter' => true,

        // The OpenTelemetry SDK will enable the instrumentation logger.
        'logger' => true,

        // The OpenTelemetry SDK will enable the instrumentation listener.
        'listeners' => [
            'client_request' => true,
            'db_query'       => true,
        ],
    ],
];
