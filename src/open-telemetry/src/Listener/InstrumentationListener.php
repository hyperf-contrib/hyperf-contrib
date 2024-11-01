<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use HyperfContrib\OpenTelemetry\Concerns\SpanRecordThrowable;
use HyperfContrib\OpenTelemetry\Switcher;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

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
    use SpanRecordThrowable;

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
}
