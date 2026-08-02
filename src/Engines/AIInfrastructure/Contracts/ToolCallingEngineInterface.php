<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Contracts;

interface ToolCallingEngineInterface
{
    public function call(string $tool, array $parameters): mixed;
    public function registerTool(string $name, callable $tool): void;
    public function listTools(): array;
}
