<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listener;

use Hyperf\Crontab\Event\AfterExecute;
use Hyperf\Event\Contract\ListenerInterface;
use OpenTelemetry\API\Trace\SpanKind;

class CrontabListener extends InstrumentationListener implements ListenerInterface
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
        if (! $this->switcher->isTracingEnabled('crontab')) {
            return;
        }

        $nowInNs = (int) (microtime(true) * 1E9);

        $this->instrumentation->tracer()->spanBuilder($event->crontab->getName())
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan()
            ->setAttributes([
                'crontab' => $event->crontab->getName(), // todo: check if this is correct
            ])
            ->end($nowInNs);
    }
}
