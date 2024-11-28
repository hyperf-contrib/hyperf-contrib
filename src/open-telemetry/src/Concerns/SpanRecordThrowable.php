<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Concerns;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

trait SpanRecordThrowable
{
    /**
     * Record exception to span.
     *
     * @param SpanInterface $span
     * @param ?Throwable $e
     * @return void
     */
    protected function spanRecordException(SpanInterface $span, ?Throwable $e = null): void
    {
        if ($e === null) {
            return;
        }

        $span->setAttributes([
            TraceAttributes::EXCEPTION_TYPE       => get_class($e),
            TraceAttributes::EXCEPTION_MESSAGE    => $e->getMessage(),
            TraceAttributes::EXCEPTION_STACKTRACE => $e->getTraceAsString(),
            TraceAttributes::CODE_FUNCTION        => $e->getFile() . ':' . $e->getLine(),
            TraceAttributes::CODE_LINENO          => $e->getLine(),
        ]);
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        $span->recordException($e);
    }
}
