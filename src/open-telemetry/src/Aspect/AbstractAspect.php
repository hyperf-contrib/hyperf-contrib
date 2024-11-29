<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Aspect;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use Hyperf\Di\Aop\AbstractAspect as BaseAbstractAspect;
use HyperfContrib\OpenTelemetry\Concerns\SpanRecordThrowable;
use HyperfContrib\OpenTelemetry\Contract\InstrumentationInterface;
use HyperfContrib\OpenTelemetry\Switcher;

abstract class AbstractAspect extends BaseAbstractAspect
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
        protected readonly InstrumentationInterface $instrumentation,
        protected readonly Switcher $switcher,
    ) {
        $this->config = $this->container->get(ConfigInterface::class);
    }
}
