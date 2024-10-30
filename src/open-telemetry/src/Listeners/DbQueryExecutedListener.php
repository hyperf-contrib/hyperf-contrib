<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listeners;

use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;

class DbQueryExecutedListener extends InstrumentationListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event):void
    {
        match($event::class) {
            QueryExecuted::class => $this->onQueryExecuted($event),
            default              => null,
        };
    }

    private function onQueryExecuted(QueryExecuted $event): void
    {
        if (! $this->switcher->isTracingEnabled('db_query')) {
            return;
        }

        $nowInNs = (int) (microtime(true) * 1E9);

        $this->instrumentation->tracer()->spanBuilder('sql ' . $event->sql)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->calculateQueryStartTime($nowInNs, $event->time))
            ->startSpan()
            ->setAttributes([
                TraceAttributes::DB_SYSTEM         => $event->connection->getDriverName(),
                TraceAttributes::DB_NAMESPACE      => $event->connection->getDatabaseName(),
                TraceAttributes::DB_OPERATION_NAME => Str::upper(Str::before($event->sql, ' ')),
                TraceAttributes::DB_USER           => $event->connection->getConfig('username'), // todo: check if it's correct
                TraceAttributes::DB_QUERY_TEXT     => $event->sql,
                TraceAttributes::SERVER_ADDRESS    => $event->connection->getConfig('host'), // todo: check if it's correct
            ])
            ->end($nowInNs);
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }
}
