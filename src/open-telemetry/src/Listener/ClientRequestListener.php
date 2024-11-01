<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listener;

use function Hyperf\Coroutine\defer;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\HttpServer\Event\RequestReceived;
use Hyperf\HttpServer\Event\RequestTerminated;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;

class ClientRequestListener extends InstrumentationListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            RequestReceived::class,
            RequestTerminated::class,
        ];
    }

    public function process(object $event): void
    {
        if (!$this->switcher->isTracingEnabled('client_request')) {
            return;
        }

        match ($event::class) {
            RequestReceived::class   => $this->onRequestReceived($event),
            RequestTerminated::class => $this->onRequestTerminated($event),
            default                  => null,
        };
    }

    protected function onRequestReceived(RequestReceived $event): void
    {
        $parent = Context::getCurrent();

        $span = $this->instrumentation->tracer()->spanBuilder($event->request->getMethod())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $headers = $event->request->getHeaders();

        $span->setAttributes([
            TraceAttributes::HTTP_REQUEST_METHOD => $event->request->getMethod(),
            TraceAttributes::URL_FULL            => (string) $event->request->getUri(),
            TraceAttributes::URL_PATH            => $event->request->getUri()->getPath(),
            TraceAttributes::URL_SCHEME          => $event->request->getUri()->getScheme(),
            TraceAttributes::SERVER_ADDRESS      => $event->request->getUri()->getHost(),
            TraceAttributes::SERVER_PORT         => $event->request->getUri()->getPort(),
            TraceAttributes::USER_AGENT_ORIGINAL => $event->request->getHeaderLine('User-Agent'),
            TraceAttributes::URL_QUERY           => $event->request->getUri()->getQuery(),
            TraceAttributes::CLIENT_ADDRESS      => (string) $event->request->getServerParams()['remote_addr'],
            ...$this->transformHeaders($event->request->getHeaders()),
        ]);

        Context::storage()->attach($span->storeInContext($parent));
    }

    protected function onRequestTerminated(RequestTerminated $event): void
    {
        if (!$scope = Context::storage()->scope()) {
            return;
        }
        defer(function () use ($scope) {
            $scope->detach();
        });

        $span = Span::fromContext($scope->context());
        if (!$span->isRecording()) {
            return;
        }
        defer(function () use ($span) {
            $span->end();
        });

        $span->setAttributes([
            TraceAttributes::HTTP_RESPONSE_STATUS_CODE => $event->response->getStatusCode(),
            TraceAttributes::HTTP_RESPONSE_BODY_SIZE   => $event->response->getBody()->getSize(),
            ...$this->transformHeaders($event->response->getHeaders()),
        ]);

        if ($event->getThrowable() !== null) {
            $this->spanRecordException($span, $event->getThrowable());
        }
    }

    /**
     * Transform headers to OpenTelemetry attributes.
     *
     * @param array<array<string>> $headers
     * @return array<string, array<string>>
     */
    private function transformHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $result["http.request.header.$key"] = $value;
        }

        return $result;
    }
}
