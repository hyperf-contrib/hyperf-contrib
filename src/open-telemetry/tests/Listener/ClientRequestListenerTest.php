<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Tests\Listener;

use Hyperf\HttpServer\Event\RequestReceived;
use Hyperf\HttpServer\Event\RequestTerminated;
use HyperfContrib\OpenTelemetry\Listener\ClientRequestListener;
use HyperfContrib\OpenTelemetry\Switcher;
use HyperfContrib\OpenTelemetry\Tests\TestCase;
use Mockery;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class ClientRequestListenerTest extends TestCase
{
    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function test_listen(): void
    {
        $container = $this->getContainer($this->getConfig());

        $listener = new ClientRequestListener(
            container: $container,
            instrumentation: $container->get(CachedInstrumentation::class),
            switcher: $container->get(Switcher::class),
        );

        [$request, $response] = [$this->getServerRequest(), $this->getServerResponse()];

        // before request
        $this->assertCount(0, $this->storage);

        $listener->process(new RequestReceived(
            $request,
            $response,
            null,
            'http'
        ));

        $listener->process(new RequestTerminated(
            $request,
            $response,
            null,
            'http'
        ));

        // after request
        $this->assertCount(1, $this->storage);

        /** @var ImmutableSpan $span */
        $span       = $this->storage[0];
        $attributes = $span->getAttributes();

        $this->assertSame('GET /path', $span->getName());
        $this->assertSame(SpanKind::KIND_SERVER, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());
    }

    protected function getServerRequest(): ServerRequestInterface
    {
        $request = Mockery::mock(ServerRequestInterface::class, [
            'getMethod' => 'GET',
            'getUri'    => Mockery::mock(UriInterface::class, [
                'getScheme' => 'http',
                'getHost'   => 'localhost',
                'getPort'   => 80,
                'getPath'   => '/path',
                'getQuery'  => 'field1=value1&field2=value2',
            ]),
            'getServerParams' => ['remote_addr' => '1.1.1.1'],
            'getHeaders'      => [
                'User-Agent' => 'testing',
            ],
        ]);

        $request->shouldReceive('getHeaderLine')->with('User-Agent')->andReturn('testing');

        return $request;
    }

    protected function getServerResponse(): ResponseInterface
    {
        return Mockery::mock(ResponseInterface::class, [
            'getStatusCode' => 200,
            'getBody'       => Mockery::mock(StreamInterface::class, [
                'getSize' => 100,
            ]),
            'getHeaders' => [
                'Content-Type' => ['application/json'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        return [
            'instrumentation' => [
                'enabled'   => true,
                'tracing'   => true,
                'listeners' => [
                    'client_request' => ['enabled' => true, 'options' => []],
                ],
            ],
        ];

    }
}
