<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Tests\Listener;

use Hyperf\Database\Connection;
use Hyperf\Database\Events\QueryExecuted;
use HyperfContrib\OpenTelemetry\Listener\DbQueryExecutedListener;
use HyperfContrib\OpenTelemetry\Switcher;
use HyperfContrib\OpenTelemetry\Tests\TestCase;
use Mockery;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\StatusData;

class DbQueryExecutedListenerTest extends TestCase
{
    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function test_db_query(): void
    {
        $container = $this->getContainer([
            'open-telemetry' => [
                'instrumentation' => [
                    'enabled'   => true,
                    'tracing'   => true,
                    'listeners' => [
                        'db_query' => [
                            'enabled' => true,
                            'options' => [
                                'combine_sql_and_bindings' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(0, $this->storage);

        (new DbQueryExecutedListener(
            $container,
            $container->get(CachedInstrumentation::class),
            $container->get(Switcher::class)
        ))->process(new QueryExecuted(
            'select * from `users` where `id` = ?',
            [1],
            0.1,
            $this->getConnection(),
        ));

        $this->assertCount(1, $this->storage);

        /** @var ImmutableSpan $span */
        $span = $this->storage[0];

        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame('sql select * from `users` where `id` = ?', $span->getName());
        $this->assertSame(StatusData::ok(), $span->getStatus()->ok());

        // attributes
        $attributes = $span->getAttributes();
        $this->assertSame('mysql', $attributes->get('db.system'));
        $this->assertSame('hyperf', $attributes->get('db.namespace'));
        $this->assertSame('SELECT', $attributes->get('db.operation.name'));
        $this->assertSame('root', $attributes->get('db.user'));
        $this->assertSame('select * from `users` where `id` = ?', $attributes->get('db.query.text'));
        $this->assertSame('select * from `users` where `id` = ?', $attributes->get('db.statement'));
        $this->assertSame('localhost', $attributes->get('server.address'));
        $this->assertSame(3306, $attributes->get('server.port'));
    }

    protected function getConnection(): Connection
    {
        return Mockery::mock(Connection::class, [
            'getDriverName'   => 'mysql',
            'getDatabaseName' => 'hyperf',
            'getName'         => 'default',
        ])
            ->shouldReceive('getConfig')->with('username')->andReturn('root')
            ->shouldReceive('getConfig')->with('host')->andReturn('localhost')
            ->shouldReceive('getConfig')->with('port')->andReturn(3306)
            ->getMock();
    }
}
