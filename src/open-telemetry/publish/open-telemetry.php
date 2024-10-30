<?php

declare(strict_types=1);

return [
    // The OpenTelemetry SDK will use this service resource to identify the service.
    'resource' => [
        'service_name'      => 'my-service',
        'service_namespace' => 'my-namespace',
    ],

    // The OpenTelemetry SDK will use this URL to send the spans to the collector.
    'exporter' => [
        'endpoint' => 'http://localhost:4317',
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
