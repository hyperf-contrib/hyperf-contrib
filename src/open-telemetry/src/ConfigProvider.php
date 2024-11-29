<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry;

class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        defined('BASE_PATH') || define('BASE_PATH', '');

        return [
            'dependencies' => [
                Contract\ExporterInterface::class        => Exporter\OtlpExporter::class,
                Contract\InstrumentationInterface::class => Factory\InstrumentationFactory::class,
                ResourceInfo::class                      => Factory\OTelResourceFactory::class,
            ],
            'listeners' => [
                Listener\DbQueryExecutedListener::class,
                Listener\ClientRequestListener::class,
                Listener\CommandListener::class,
                Listener\CrontabListener::class,
            ],
            'aspects' => [
                Aspect\RedisAspect::class,
                Aspect\GuzzleClientAspect::class,
            ],
            'publish' => [
                [
                    'id'          => 'config',
                    'description' => 'The config for OpenTelemetry.',
                    'source'      => __DIR__ . '/../publish/open-telemetry.php',
                    'destination' => BASE_PATH . '/config/autoload/open-telemetry.php',
                ],
            ],
        ];
    }
}
