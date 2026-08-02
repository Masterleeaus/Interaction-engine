<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\ExecutionEngineInterface;
class ExecutionEngine implements ExecutionEngineInterface
{
    private array $history = [];

    public function execute(string $task, array $parameters): array
    {
        $result = ['status' => 'success', 'result' => []];
        $this->history[] = compact('task', 'parameters', 'result');
        return $result;
    }

    public function executeBatch(array $tasks): array
    {
        $results = [];
        foreach ($tasks as $task) {
            $results[] = $this->execute($task['name'], $task['parameters'] ?? []);
        }
        return $results;
    }

    public function getExecutionHistory(): array
    {
        return $this->history;
    }
}
