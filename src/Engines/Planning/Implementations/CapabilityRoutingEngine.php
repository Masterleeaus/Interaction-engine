<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\CapabilityRoutingEngineInterface;

class CapabilityRoutingEngine implements CapabilityRoutingEngineInterface
{
    private array $handlers = [];

    public function __construct(private readonly \TitanZero\Interaction\Contracts\PolicyEngineInterface $policyEngine) {}

    public function route(string $capability, array $payload): mixed
    {
        if (!isset($this->handlers[$capability])) {
            throw new \RuntimeException("No handler for capability '{$capability}'");
        }
        $decision = $this->policyEngine->decide($capability, $payload);
        if (!$decision->allowed) {
            throw new \RuntimeException('Capability route denied: ' . implode('; ', $decision->reasons));
        }
        return call_user_func($this->handlers[$capability], $payload);
    }

    public function registerHandler(string $capability, callable $handler): void
    {
        if (trim($capability) === '') {
            throw new \InvalidArgumentException('Capability name is required.');
        }
        $this->handlers[$capability] = $handler;
    }

    public function listCapabilities(): array
    {
        return array_keys($this->handlers);
    }
}
