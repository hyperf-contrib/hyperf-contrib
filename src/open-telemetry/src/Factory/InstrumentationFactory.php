<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Factory;

use Hyperf\Contract\ContainerInterface;
use HyperfContrib\OpenTelemetry\Contract\ExporterInterface;
use HyperfContrib\OpenTelemetry\Contract\InstrumentationInterface;
use HyperfContrib\OpenTelemetry\Instrumentation;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use OpenTelemetry\SemConv\Version;

class InstrumentationFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): InstrumentationInterface
    {
        Context::setStorage(new SwooleContextStorage(new ContextStorage()));

        $container->get(ExporterInterface::class)->configure();

        return new Instrumentation(
            instrumentation: $this->createInstrumentation(),
        );
    }

    protected function createInstrumentation(): CachedInstrumentation
    {
        return new CachedInstrumentation(
            name: 'hyperf-contrib/open-telemetry',
            schemaUrl: Version::VERSION_1_27_0->url(),
        );
    }
}
