<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\DispatchEngineInterface;

class DispatchEngine implements DispatchEngineInterface
{
    private array $tasks = [];

    public function __construct(private readonly \TitanZero\Engines\Planning\Contracts\ExecutionEngineInterface $executor) {}

    public function dispatch(string $task, array $parameters): string
    {
        if (trim($task) === '') {
            throw new \InvalidArgumentException('Task capability is required.');
        }
        $id = 'task_' . bin2hex(random_bytes(10));
        $this->tasks[$id] = [
            'id' => $id,
            'task' => $task,
            'parameters' => $parameters,
            'status' => 'pending',
            'created_at' => gmdate(DATE_ATOM),
        ];
        return $id;
    }

    public function process(string $taskId): array
    {
        if (!isset($this->tasks[$taskId])) {
            return ['id' => $taskId, 'status' => 'not_found'];
        }
        if ($this->tasks[$taskId]['status'] !== 'pending') {
            return $this->tasks[$taskId];
        }
        $this->tasks[$taskId]['status'] = 'running';
        $this->tasks[$taskId]['started_at'] = gmdate(DATE_ATOM);
        try {
            $result = $this->executor->execute($this->tasks[$taskId]['task'], $this->tasks[$taskId]['parameters']);
        } catch (\Throwable $error) {
            $this->tasks[$taskId]['status'] = 'failed';
            $this->tasks[$taskId]['error'] = $error->getMessage();
            $this->tasks[$taskId]['completed_at'] = gmdate(DATE_ATOM);
            return $this->tasks[$taskId];
        }
        $this->tasks[$taskId]['execution'] = $result;
        $this->tasks[$taskId]['status'] = match ($result['status'] ?? null) {
            'success', 'duplicate' => 'completed',
            'denied', 'rejected' => 'rejected',
            default => 'failed',
        };
        $this->tasks[$taskId]['completed_at'] = gmdate(DATE_ATOM);
        return $this->tasks[$taskId];
    }

    public function processPending(): array
    {
        $results = [];
        foreach ($this->listPendingTasks() as $task) {
            $results[] = $this->process($task['id']);
        }
        return $results;
    }

    public function getDispatchStatus(string $taskId): array
    {
        return $this->tasks[$taskId] ?? ['status' => 'not_found'];
    }

    public function listPendingTasks(): array
    {
        return array_values(array_filter($this->tasks, static fn(array $task): bool => $task['status'] === 'pending'));
    }
}
