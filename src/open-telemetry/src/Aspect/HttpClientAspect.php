<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry\Aspect;

use GuzzleHttp\Client;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Stringable\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HttpClientAspect extends AbstractAspect
{
    public array $classes = [
        Client::class . '::request',
        Client::class . '::requestAsync',
    ];

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @throws Throwable
     * @return mixed
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
            TraceAttributes::HTTP_REQUEST_METHOD => $method,
            TraceAttributes::URL_PATH            => $uri,
            'http.request.headers'               => $headers,
        ]);

        // response
        $result = $proceedingJoinPoint->process();
        if ($result instanceof ResponseInterface) {
            $span->setAttributes([
                TraceAttributes::HTTP_RESPONSE_STATUS_CODE => $result->getStatusCode(),
                'http.response.headers'                    => $result->getHeaders(),
                'http.response.body'                       => $this->getResponsePayload($result),
            ]);

            try {
                $result = $proceedingJoinPoint->process();
                $span->setStatus(StatusCode::STATUS_OK);
            } catch (\Throwable $e) {
                $this->spanRecordException($span, $e);

                throw $e;
            } finally {
                $span->end();
            }
        }

        $result->getBody()->rewind();

        return $result;
    }

    /**
     * @param ResponseInterface $response
     * @return mixed|string
     */
    private function getResponsePayload(ResponseInterface $response)
    {
        $stream = $response->getBody();

        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            $content = $stream->getContents();
        } catch (Throwable $e) {
            return 'Purged By OpenTelemetry: ' . $e->getMessage();
        }

        if (empty($content)) {
            return 'Empty Response';
        }

        if (! $this->contentWithinLimits($content)) {
            return 'Purged By OpenTelemetry';
        }
        if (
            is_array(json_decode($content, true))
            && json_last_error() === JSON_ERROR_NONE
        ) {
            return json_decode($content, true);
        }
        if (Str::startsWith(strtolower($response->getHeaderLine('content-type') ?: ''), 'text/plain')) {
            return $content;
        }

        return 'HTML Response';
    }

    private function contentWithinLimits(string $content): bool
    {
        return mb_strlen($content) / 1000 <= 64;
    }
}
