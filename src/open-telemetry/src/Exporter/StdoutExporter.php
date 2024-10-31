<?php

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
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportFactoryInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricExporter\ConsoleMetricExporterFactory;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporterFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessorFactory;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

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
    ) {
        $this->config = $this->container->get(ConfigInterface::class);
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
            ->setResource($resource)
            ->addReader($meterReader)
            ->build();

        $tracerProvider = TracerProvider::builder()
            ->addSpanProcessor($spanProcessor)
            ->setResource($resource)
            ->build();

        $loggerProvider = LoggerProvider::builder()
            ->setResource($resource)
            ->addLogRecordProcessor(
                new BatchLogRecordProcessor($logExporter, Clock::getDefault())
            )
            ->build();

        //Context::setStorage(new SwooleContextStorage(new ContextStorage()));

        Sdk::builder()
            ->setTracerProvider($tracerProvider)
            ->setMeterProvider($meterProvider)
            ->setLoggerProvider($loggerProvider)
            ->setPropagator(TraceContextPropagator::getInstance())
            ->setAutoShutdown(true)
            ->buildAndRegisterGlobal();
    }
}
