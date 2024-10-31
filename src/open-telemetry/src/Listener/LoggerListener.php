<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listener;

use Hyperf\Event\Contract\ListenerInterface;

class LoggerListener extends InstrumentationListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
        ];
    }

    public function process(object $event): void
    {
        // todo: implement process() method
    }

}
