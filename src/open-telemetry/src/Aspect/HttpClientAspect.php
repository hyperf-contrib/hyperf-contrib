<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Aspect;

use GuzzleHttp\Client;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Di\Exception\Exception;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HttpClientAspect extends AbstractAspect
{
    public array $classes = [
        Client::class . '::requestAsync',
    ];

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @throws Throwable
     * @throws Exception
     * @return mixed|ResponseInterface
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switcher->isTracingEnabled('guzzle') === false) {
            return $proceedingJoinPoint->process();
        }

        $arguments = $proceedingJoinPoint->arguments['keys'];

        $method  = $arguments['method']             ?? 'GET';
        $uri     = $arguments['uri']                ?? '';
        $headers = $arguments['options']['headers'] ?? [];

        // request
        $span = $this->instrumentation->tracer()->spanBuilder($method . ' ' . $uri)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $span->setAttributes([
            TraceAttributes::HTTP_REQUEST_METHOD      => $method,
            TraceAttributes::URL_FULL                 => $uri,
            TraceAttributes::NETWORK_PROTOCOL_VERSION => $arguments['protocol_version'] ?? '',
            TraceAttributes::USER_AGENT_ORIGINAL      => $headers['User-Agent']         ?? '',
            TraceAttributes::HTTP_REQUEST_BODY_SIZE   => $headers['Content-Length']     ?? '',
            TraceAttributes::SERVER_ADDRESS           => parse_url($uri, PHP_URL_HOST),
            TraceAttributes::SERVER_PORT              => parse_url($uri, PHP_URL_PORT),
            TraceAttributes::URL_PATH                 => parse_url($uri, PHP_URL_PATH),
        ]);

        // response
        $response = $proceedingJoinPoint->process();
        if ($response instanceof ResponseInterface) {
            $span->setAttributes([
                TraceAttributes::HTTP_RESPONSE_STATUS_CODE => $response->getStatusCode(),
                TraceAttributes::NETWORK_PROTOCOL_VERSION  => $response->getProtocolVersion(),
                TraceAttributes::HTTP_RESPONSE_BODY_SIZE   => $response->getHeaderLine('Content-Length'),
            ]);
            $response->getBody()->rewind();
        }

        try {
            if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 600) {
                $span->setStatus(StatusCode::STATUS_ERROR);
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }
        } catch (\Throwable $e) {
            $this->spanRecordException($span, $e);

            throw $e;
        } finally {
            $span->end();
        }

        return $response;
    }
}
