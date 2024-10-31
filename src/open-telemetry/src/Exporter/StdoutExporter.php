<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use HyperfContrib\OpenTelemetry\Contract\ExporterInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricExporter\ConsoleMetricExporterFactory;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\TracerProvider;

/**
 * todo: Implement the OpenTelemetry exporter.
 */
class StdoutExporter implements ExporterInterface
{
    protected ConfigInterface $config;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly ResourceInfo $resource,
    ) {
        $this->config = $this->container->get(ConfigInterface::class);
    }

    public function configure(): void
    {
        $spanProcessor = (new SpanProcessorFactory())->create(
            (new ConsoleSpanExporterFactory())->create()
        );

        $logExporter = new LogsExporter(
            (new OtlpHttpTransportFactory())->create(
                endpoint: $this->config->get('open-telemetry.exporter.otlp.endpoint') . '/v1/logs',
                contentType:  'application/x-protobuf',
                compression: TransportFactoryInterface::COMPRESSION_GZIP,
            )
        );

        $meterReader = new ExportingReader(
            (new ConsoleMetricExporterFactory())->create(),
        );

        $meterProvider = MeterProvider::builder()
            ->setResource($this->resource)
            ->addReader($meterReader)
            ->build();

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor($spanProcessor)
            ->setResource($this->resource)
            ->build();

        $loggerProvider = LoggerProvider::builder()
            ->setResource($this->resource)
            ->addLogRecordProcessor(
                new BatchLogRecordProcessor($logExporter, Clock::getDefault())
            )
            ->build();

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }
}
