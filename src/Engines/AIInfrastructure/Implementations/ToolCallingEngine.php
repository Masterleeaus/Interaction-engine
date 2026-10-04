<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Implementations;

use TitanZero\Engines\AIInfrastructure\Contracts\ToolCallingEngineInterface;
use TitanZero\Interaction\Contracts\PolicyEngineInterface;

class ToolCallingEngine implements ToolCallingEngineInterface
{
    private array $tools = [];

    public function __construct(private readonly PolicyEngineInterface $policyEngine) {}

    public function call(string $tool, array $parameters): mixed
    {
        if (!isset($this->tools[$tool])) {
            throw new \RuntimeException("Tool '{$tool}' not found");
        }
        $decision = $this->policyEngine->decide($tool, $parameters);
        if (!$decision->allowed) {
            throw new \RuntimeException('Tool call denied: ' . implode('; ', $decision->reasons));
        }
        return ($this->tools[$tool])($parameters);
    }

    public function registerTool(string $name, callable $tool): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Tool capability name is required.');
        }
        $this->tools[$name] = $tool;
    }

    public function listTools(): array
    {
        return array_keys($this->tools);
    }
}
