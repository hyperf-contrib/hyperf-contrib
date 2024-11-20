<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Aspect;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use Hyperf\Stringable\Str;
use HyperfContrib\OpenTelemetry\Propagator\HeadersPropagator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GuzzleClientAspect extends AbstractAspect
{
    public array $classes = [
        Client::class . '::transfer',
    ];

    /**
     * @throws \Throwable
     * @throws Exception
     * @return mixed|ResponseInterface
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switcher->isTracingEnabled('guzzle') === false) {
            return $proceedingJoinPoint->process();
        }

        $parentContext = Context::getCurrent();

        /**
         * @var RequestInterface $request
         */
        $request = $proceedingJoinPoint->arguments['keys']['request'];
        $method  = $request->getMethod();
        $uri     = (string) $request->getUri();

        // request
        $span = $this->instrumentation->tracer()
            ->spanBuilder($method . ' ' . $uri)
            ->setParent($parentContext)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $context = $span->storeInContext($parentContext);

        TraceContextPropagator::getInstance()->inject($request, HeadersPropagator::instance(), $context);

        if ($request instanceof RequestInterface) {
            $span->setAttributes([
                TraceAttributes::HTTP_REQUEST_METHOD => $method,
                TraceAttributes::URL_FULL            => $uri,
                TraceAttributes::URL_PATH            => $request->getUri()->getPath(),
                TraceAttributes::URL_SCHEME          => $request->getUri()->getScheme(),
                TraceAttributes::SERVER_ADDRESS      => $request->getUri()->getHost(),
                TraceAttributes::SERVER_PORT         => $request->getUri()->getPort(),
                TraceAttributes::USER_AGENT_ORIGINAL => $request->getHeaderLine('User-Agent'),
                TraceAttributes::URL_QUERY           => $request->getUri()->getQuery(),
                ...$this->transformHeaders('request', $request->getHeaders()),
            ]);
        }

        // response
        $promise = $proceedingJoinPoint->process();
        if ($promise instanceof PromiseInterface) {
            $promise->then(
                onFulfilled: function (ResponseInterface $response) use ($span) {
                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                    $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $response->getHeaderLine('Content-Length'));
                    $span->setAttributes($this->transformHeaders('response', $response->getHeaders()));
                    if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 600) {
                        $span->setStatus(StatusCode::STATUS_ERROR);
                    }
                    $span->end();

                    return $response;
                },
                onRejected: function (\Throwable $t) use ($span) {
                    $this->spanRecordException($span, $t);
                    $span->end();

                    throw $t;
                }
            );
        }

        return $promise;
    }

    /**
     * Transform headers to OpenTelemetry attributes.
     *
     * @param array<array<string>> $headers
     * @return array<string, array<string>>
     */
    private function transformHeaders(string $type, array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $key = Str::lower($key);
            if ($this->canTransformHeaders($type, $key)) {
                $result["http.{$type}.header.{$key}"] = $value;
            }
        }

        return $result;
    }

    private function canTransformHeaders(string $type, string $key): bool
    {
        $headers = (array) $this->config->get("open-telemetry.instrumentation.features.guzzle.options.headers.{$type}", ['*']);
        foreach ($headers as $header) {
            if (Str::is(Str::lower($header), $key)) {
                return true;
            }
        }

        return false;
    }
}
