<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Factory;

use Hyperf\Contract\ContainerInterface;
use HyperfContrib\OpenTelemetry\Contract\ExporterInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextStorage;
use OpenTelemetry\Contrib\Context\Swoole\SwooleContextStorage;
use OpenTelemetry\SemConv\Version;

class CachedInstrumentationFactory
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): CachedInstrumentation
    {
        Context::setStorage(new SwooleContextStorage(new ContextStorage()));

        $container->get(ExporterInterface::class)->configure();

        return new CachedInstrumentation(
            name: 'hyperf-contrib/open-telemetry',
            schemaUrl: Version::VERSION_1_27_0->url(),
            attributes: [
                'instrumentation.name' => 'hyperf-contrib/open-telemetry',
            ],
        );
    }
}
