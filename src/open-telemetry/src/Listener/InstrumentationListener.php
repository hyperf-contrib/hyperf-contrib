<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use HyperfContrib\OpenTelemetry\Switcher;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

/**
 * Class InstrumentationListener.
 *
 * @package HyperfContrib\OpenTelemetry\Listener
 * @property-read ConfigInterface $config
 * @property-read ContainerInterface $container
 * @property-read CachedInstrumentation $instrumentation
 * @property-read Switcher $switcher
 */
abstract class InstrumentationListener
{
    protected readonly ConfigInterface $config;

    /**
     * InstrumentationListener constructor.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __construct(
        protected readonly ContainerInterface $container,
        protected readonly CachedInstrumentation $instrumentation,
        protected readonly Switcher $switcher,
    ) {
        $this->config = $this->container->get(ConfigInterface::class);
    }

    /**
     * Record exception to span.
     *
     * @param SpanInterface $span
     * @param Throwable $e
     * @return void
     */
    protected function spanRecordException(SpanInterface $span, Throwable $e): void
    {
        $span->setAttributes([
            TraceAttributes::EXCEPTION_TYPE       => get_class($e),
            TraceAttributes::EXCEPTION_MESSAGE    => $e->getMessage(),
            TraceAttributes::EXCEPTION_STACKTRACE => $e->getTraceAsString(),
            TraceAttributes::CODE_FUNCTION        => $e->getFile() . ':' . $e->getLine(),
            TraceAttributes::CODE_LINENO          => $e->getLine(),
        ]);
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $span->recordException($e);
    }
}
