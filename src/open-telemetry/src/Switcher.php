<?php

declare(strict_types=1);

namespace HyperfContrib\OpenTelemetry;

use Hyperf\Contract\ConfigInterface;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

class Switcher
{
    public function __construct(
        protected readonly CachedInstrumentation $instrumentation,
        protected readonly ConfigInterface $config,
    ) {
    }

    /**
     * Check if the instrumentation is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->get('open-telemetry.instrumentation.enabled', true);
    }

    /**
     * Check if the tracing is enabled.
     *
     * @param ?string $key
     * @return bool
     */
    public function isTracingEnabled(?string $key = null): bool
    {
        if (null === $key) {
            return $this->instrumentation->tracer()->isEnabled()
                    && $this->isEnabled()
                    && $this->config->get('open-telemetry.instrumentation.tracing', true);
        }

        return $this->isTracingEnabled()
                && $this->config->get("open-telemetry.instrumentation.features.{$key}.enabled", true);
    }
}
