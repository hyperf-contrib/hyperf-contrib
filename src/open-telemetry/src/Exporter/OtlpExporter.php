<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Exporter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use HyperfContrib\OpenTelemetry\Contract\ExporterInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

class OtlpExporter implements ExporterInterface
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
        $endpoint = $this->config->get('open-telemetry.exporter.otlp.endpoint', 'http://localhost:4318');

        $spanExporter = new SpanExporter(
            (new OtlpHttpTransportFactory())->create(
                endpoint: $endpoint . '/v1/traces',
                contentType:'application/x-protobuf',
                compression: TransportFactoryInterface::COMPRESSION_GZIP,
            )
            // (new StreamTransportFactory())->create('php://stdout', 'application/json');
        );

        $logExporter = new LogsExporter(
            (new OtlpHttpTransportFactory())->create(
                endpoint: $endpoint . '/v1/logs',
                contentType:  'application/x-protobuf',
                compression: TransportFactoryInterface::COMPRESSION_GZIP,
            )
        );

        $reader = new ExportingReader(
            new MetricExporter(
                (new OtlpHttpTransportFactory())->create(
                    endpoint: $endpoint . '/v1/metrics',
                    contentType: 'application/x-protobuf',
                    compression: TransportFactoryInterface::COMPRESSION_GZIP,
                )
            )
        );

        $meterProvider = MeterProvider::builder()
            ->setResource($this->resource)
            ->addReader($reader)
            ->build();

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(
                new BatchSpanProcessor(
                    $spanExporter,
                    Clock::getDefault(),
                    BatchSpanProcessor::DEFAULT_MAX_QUEUE_SIZE,
                    BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY,
                    BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT,
                    BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE,
                    true,
                )
            )
            ->setResource($this->resource)
            ->setSampler($this->config->get('open-telemetry.sampler', new ParentBased(new AlwaysOnSampler())))
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
