<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface DispatchEngineInterface
{
    public function dispatch(string $task, array $parameters): string;
    public function process(string $taskId): array;
    public function processPending(): array;
    public function getDispatchStatus(string $taskId): array;
    public function listPendingTasks(): array;
}
