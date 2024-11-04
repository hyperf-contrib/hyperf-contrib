<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Aspect;

use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Redis\Redis;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;

class RedisAspect extends AbstractAspect
{
    public array $classes = [
        Redis::class . '::__call',
    ];

    /**
     * @throws \Hyperf\Di\Exception\Exception
     * @throws \Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switcher->isTracingEnabled('redis') === false) {
            return $proceedingJoinPoint->process();
        }

        $args        = $proceedingJoinPoint->getArguments();
        $command     = $args[0];
        $commandFull = $command . ' ' . implode(' ', $args[1]);
        $poolName    = (fn () => $this->poolName ?? 'default')->call($proceedingJoinPoint->getInstance());

        $span = $this->instrumentation->tracer()->spanBuilder('redis ' . $command)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $span->setAttributes([
            TraceAttributes::DB_SYSTEM => 'redis',
            //TraceAttributes::DB_NAMESPACE => $proceedingJoinPoint->getTarget()->getDatabaseName(),
            //TraceAttributes::DB_NAMESPACE      => $proceedingJoinPoint->getTarget()->getDatabaseName(),
            TraceAttributes::DB_OPERATION_NAME => Str::upper($command),
            //TraceAttributes::DB_USER           => $proceedingJoinPoint->getTarget()->getConfig('username'),
            TraceAttributes::DB_QUERY_TEXT => $commandFull,
            TraceAttributes::DB_STATEMENT  => $commandFull,
            //TraceAttributes::SERVER_ADDRESS    => $proceedingJoinPoint->getTarget()->getConfig('host'),
            //TraceAttributes::SERVER_PORT       => $proceedingJoinPoint->getTarget()->getConfig('port'),
            'hyperf.redis.pool' => $poolName,
        ]);

        try {
            $result = $proceedingJoinPoint->process();
        } catch (\Throwable $e) {
            $this->spanRecordException($span, $e);

            throw $e;
        } finally {
            $span->end();
        }

        return $result;
    }
}
