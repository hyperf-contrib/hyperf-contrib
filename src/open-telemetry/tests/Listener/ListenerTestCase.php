<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Tests\Listener;

use Hyperf\Config\Config;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\ContainerInterface;
use HyperfContrib\OpenTelemetry\Switcher;
use HyperfContrib\OpenTelemetry\Tests\TestCase;
use Mockery;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SemConv\Version;

class ListenerTestCase extends TestCase
{
    /**
     * @param array<string, mixed> $config
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @return \Hyperf\Contract\ContainerInterface
     */
    protected function getContainer(array $config): ContainerInterface
    {
        $container = Mockery::mock(ContainerInterface::class);

        $container->shouldReceive('get')->with(ConfigInterface::class)->andReturns(
            new Config($config)
        );

        $container->shouldReceive('get')->with(CachedInstrumentation::class)->andReturns(
            new CachedInstrumentation(
                name: 'hyperf-contrib/open-telemetry',
                schemaUrl: Version::VERSION_1_27_0->url(),
                attributes: [
                    'instrumentation.name' => 'hyperf-contrib/open-telemetry',
                ],
            ),
        );

        $container->shouldReceive('get')->with(Switcher::class)->andReturns(
            new Switcher(
                $container->get(CachedInstrumentation::class),
                $container->get(ConfigInterface::class),
            )
        );

        return $container;
    }
}
