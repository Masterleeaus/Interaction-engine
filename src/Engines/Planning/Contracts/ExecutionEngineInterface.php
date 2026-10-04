<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface ExecutionEngineInterface
{
    public function registerHandler(string $task, callable $handler): void;
    public function execute(string $task, array $parameters): array;
    public function executeBatch(array $tasks): array;
    public function getExecutionHistory(): array;
}
