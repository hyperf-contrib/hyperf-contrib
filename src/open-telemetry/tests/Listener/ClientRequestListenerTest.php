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
        $this->assertSame('GET', $attributes->get('http.request.method'));
        $this->assertSame('http://localhost:80/path?field1=value1&field2=value2', $attributes->get('url.full'));
        $this->assertSame('/path', $attributes->get('url.path'));
        $this->assertSame('http', $attributes->get('url.scheme'));
        $this->assertSame('localhost', $attributes->get('server.address'));
        $this->assertSame(80, $attributes->get('server.port'));
        $this->assertSame('testing', $attributes->get('user_agent.original'));
        $this->assertSame('field1=value1&field2=value2', $attributes->get('url.query'));
        $this->assertSame('1.1.1.1', $attributes->get('client.address'));
        $this->assertSame(200, $attributes->get('http.response.status_code'));
        $this->assertSame(100, $attributes->get('http.response.body.size'));
        $this->assertSame('testing', $attributes->get('http.request.header.user-agent'));
        $this->assertSame('x-custom', $attributes->get('http.request.header.x-custom-header'));
        $this->assertSame(['x-custom1'], $attributes->get('http.request.header.x-custom-header1'));
        $this->assertNull($attributes->get('http.request.header.illegal-header'));
        $this->assertSame(['application/json'], $attributes->get('http.response.header.content-type'));
        $this->assertSame('x-custom', $attributes->get('http.response.header.x-custom-header'));
        $this->assertSame(['x-custom1'], $attributes->get('http.response.header.x-custom-header1'));
        $this->assertNull($attributes->get('http.response.header.illegal-header'));
    }

    protected function getServerRequest(): ServerRequestInterface
    {
        $request = Mockery::mock(ServerRequestInterface::class, [
            'getMethod' => 'GET',
            'getUri'    => Mockery::mock(UriInterface::class, [
                'getScheme'  => 'http',
                'getHost'    => 'localhost',
                'getPort'    => 80,
                'getPath'    => '/path',
                'getQuery'   => 'field1=value1&field2=value2',
                '__toString' => 'http://localhost:80/path?field1=value1&field2=value2',
            ]),
            'getServerParams' => ['remote_addr' => '1.1.1.1'],
            'getHeaders'      => [
                'User-Agent'       => 'testing',
                'X-Custom-Header'  => 'x-custom',
                'X-Custom-Header1' => ['x-custom1'],
                'Illegal-Header'   => 'illegal',
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
                'Content-Type'     => ['application/json'],
                'X-Custom-Header'  => 'x-custom',
                'X-Custom-Header1' => ['x-custom1'],
                'Illegal-Header'   => 'illegal',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getConfig(): array
    {
        return [
            'open-telemetry' => [
                'instrumentation' => [
                    'enabled'   => true,
                    'tracing'   => true,
                    'listeners' => [
                        'client_request' => ['enabled' => true, 'options' => [
                            'headers' => [
                                'request'  => ['User-Agent', 'x-custom-*',],
                                'response' => ['Content-Type', 'x-custom-*',],
                            ],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
