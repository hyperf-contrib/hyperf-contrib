<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listeners;

use Hyperf\Command\Event\AfterExecute;
use Hyperf\Event\Contract\ListenerInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;

class CommandListener extends InstrumentationListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            AfterExecute::class,
        ];
    }

    public function process(object $event): void
    {
        match($event::class) {
            AfterExecute::class => $this->onAfterExecute($event),
            default             => null,
        };
    }

    protected function onAfterExecute(AfterExecute $event): void
    {
        if (! $this->switcher->isTracingEnabled('command')) {
            return;
        }

        $nowInNs = (int) (microtime(true) * 1E9);

        $this->instrumentation->tracer()->spanBuilder($event->getCommand()->getName())
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan()
            ->setAttributes([
                TraceAttributes::PROCESS_COMMAND => $event->getCommand()->getName(),
            ])->end($nowInNs);
    }
}
