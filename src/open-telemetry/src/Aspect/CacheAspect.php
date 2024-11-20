<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Aspect;

use Hyperf\Cache\Cache;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use function Hyperf\Tappable\tap;
use OpenTelemetry\API\Trace\SpanKind;

class CacheAspect extends AbstractAspect
{
    public array $classes = [
        Cache::class . '::__call',
    ];

    /**
     * @throws \Hyperf\Di\Exception\Exception
     * @throws \Throwable
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switcher->isTracingEnabled('cache') === false) {
            return $proceedingJoinPoint->process();
        }

        $method    = $proceedingJoinPoint->arguments['keys']['name'];
        $arguments = $proceedingJoinPoint->arguments['keys']['arguments'];

        $span = $this->instrumentation->tracer()->spanBuilder('cache ' . $method)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttributes([
            'cache.command' => $method . ' ' . implode(' ', $arguments),
        ]);

        return tap($proceedingJoinPoint->process(), function () use ($span) {
            $span->end();
        });
    }

    private function build(array $arguments): string
    {
        return implode(':', $arguments);
    }
}
