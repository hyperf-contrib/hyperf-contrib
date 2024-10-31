<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Listener;

use Hyperf\Collection\Arr;
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

    public function process(object $event): void
    {
        match ($event::class) {
            QueryExecuted::class => $this->onQueryExecuted($event),
            default              => null,
        };
    }

    protected function onQueryExecuted(QueryExecuted $event): void
    {
        if (!$this->switcher->isTracingEnabled('db_query')) {
            return;
        }

        // todo: check if the switcher is on
        // @phpstan-ignore-next-line
        $sql = false ? $this->combineSqlAndBindings($event) : $event->sql;

        $this->instrumentation->tracer()->spanBuilder('sql ' . $event->sql)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan()
            ->setAttributes([
                TraceAttributes::DB_SYSTEM         => $event->connection->getDriverName(),
                TraceAttributes::DB_NAMESPACE      => $event->connection->getDatabaseName(),
                TraceAttributes::DB_OPERATION_NAME => Str::upper(Str::before($event->sql, ' ')),
                TraceAttributes::DB_USER           => $event->connection->getConfig('username'),
                TraceAttributes::DB_QUERY_TEXT     => $sql,
                TraceAttributes::DB_STATEMENT      => $sql,
                TraceAttributes::SERVER_ADDRESS    => $event->connection->getConfig('host'),
                TraceAttributes::SERVER_PORT       => $event->connection->getConfig('port'),
            ])->end();
    }

    protected function combineSqlAndBindings(QueryExecuted $event): string
    {
        $sql = $event->sql;
        if (!Arr::isAssoc($event->bindings)) {
            foreach ($event->bindings as $value) {
                $sql = Str::replaceFirst('?', "'{$value}'", $sql);
            }
        }

        return $sql;
    }
}
