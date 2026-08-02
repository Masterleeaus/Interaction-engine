<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Implementations;

use TitanZero\Engines\AIInfrastructure\Contracts\ToolCallingEngineInterface;
class ToolCallingEngine implements ToolCallingEngineInterface
{
    private array $tools = [];

    public function call(string $tool, array $parameters): mixed
    {
        if (!isset($this->tools[$tool])) {
            throw new \RuntimeException("Tool '{$tool}' not found");
        }
        return call_user_func($this->tools[$tool], $parameters);
    }

    public function registerTool(string $name, callable $tool): void
    {
        $this->tools[$name] = $tool;
    }

    public function listTools(): array
    {
        return array_keys($this->tools);
    }
}
