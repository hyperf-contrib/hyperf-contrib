<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Exporter;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

class OtlpExporter implements ExporterInterface
{
    public function __construct(
        protected readonly ConfigInterface $config,
    ) {
    }

    public function configure(): void
    {
        $resource = ResourceInfoFactory::defaultResource()->merge(ResourceInfo::create(Attributes::create(
            $this->config->get('open-telemetry.resource', [
                ResourceAttributes::SERVICE_NAMESPACE           => $this->config->get('app_name'),
                ResourceAttributes::SERVICE_NAME                => $this->config->get('app_name'),
                ResourceAttributes::DEPLOYMENT_ENVIRONMENT_NAME => $this->config->get('app_env'),
            ])
        )));

        $spanExporter = new SpanExporter(
            (new OtlpHttpTransportFactory())->create(
                endpoint: $this->config->get('open-telemetry.exporter.otlp.endpoint') . '/v1/traces',
                contentType:'application/x-protobuf',
                compression: TransportFactoryInterface::COMPRESSION_GZIP,
            )
            // (new StreamTransportFactory())->create('php://stdout', 'application/json');
        );

        $logExporter = new LogsExporter(
            (new OtlpHttpTransportFactory())->create(
                endpoint: $this->config->get('open-telemetry.exporter.otlp.endpoint') . '/v1/logs',
                contentType:  'application/x-protobuf',
                compression: TransportFactoryInterface::COMPRESSION_GZIP,
            )
        );

        $reader = new ExportingReader(
            new MetricExporter(
                (new OtlpHttpTransportFactory())->create(
                    endpoint: $this->config->get('open-telemetry.exporter.otlp.endpoint') . '/v1/metrics',
                    contentType: 'application/x-protobuf',
                    compression: TransportFactoryInterface::COMPRESSION_GZIP,
                )
            )
        );

        $meterProvider = MeterProvider::builder()
            ->setResource($resource)
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
            ->setResource($resource)
            ->setSampler(new ParentBased(new TraceIdRatioBasedSampler(0.1))) // todo: config sampler
            //->setSampler(new TraceIdRatioBasedSampler(0.1))
            ->build();

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
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
