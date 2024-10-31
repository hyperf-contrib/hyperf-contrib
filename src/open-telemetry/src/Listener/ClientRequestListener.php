<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\HttpServer\Event\RequestReceived;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;

class ClientRequestListener extends InstrumentationListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            RequestReceived::class,
        ];
    }

    public function process(object $event): void
    {
        match ($event::class) {
            RequestReceived::class => $this->onRequestReceived($event),
            default                => null,
        };
    }

    protected function onRequestReceived(RequestReceived $event): void
    {
        if (!$this->switcher->isTracingEnabled('client_request')) {
            return;
        }

        $nowInNs = (int) (microtime(true) * 1E9);

        $span = $this->instrumentation->tracer()->spanBuilder($event->request->getMethod())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
        $scope = $span->activate();

        $span->setAttributes([
            TraceAttributes::HTTP_REQUEST_METHOD => $event->request->getMethod(),
            TraceAttributes::URL_FULL            => (string) $event->request->getUri(),
            TraceAttributes::URL_PATH            => $event->request->getUri()->getPath(),
            TraceAttributes::USER_AGENT_NAME     => $event->request->getHeaderLine('User-Agent'),
            TraceAttributes::USER_AGENT_ORIGINAL => $event->request->getHeaderLine('User-Agent'),
        ])->end($nowInNs);
        $scope->detach();
    }
}
