<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\ExecutionEngineInterface;
use TitanZero\Interaction\Contracts\PolicyEngineInterface;

class ExecutionEngine implements ExecutionEngineInterface
{
    private array $history = [];
    private array $handlers = [];
    private array $completedByKey = [];

    public function __construct(private readonly PolicyEngineInterface $policyEngine) {}

    public function registerHandler(string $task, callable $handler): void
    {
        if (trim($task) === '') {
            throw new \InvalidArgumentException('Task capability is required.');
        }
        $this->handlers[$task] = $handler;
    }

    public function execute(string $task, array $parameters): array
    {
        $executionId = bin2hex(random_bytes(12));
        if (trim($task) === '') {
            return $this->record($executionId, $task, $parameters, 'rejected', ['reason' => 'Task capability is required.']);
        }

        $decision = $this->policyEngine->decide($task, $parameters);
        if (!$decision->allowed) {
            return $this->record($executionId, $task, $parameters, 'denied', ['reasons' => $decision->reasons]);
        }
        if (!isset($this->handlers[$task])) {
            return $this->record($executionId, $task, $parameters, 'unavailable', ['reason' => 'No execution handler is registered.']);
        }

        $rawKey = (string) ($parameters['_idempotency_key'] ?? $parameters['idempotency_key'] ?? '');
        $tenantId = (string) ($parameters['_context']['tenant_id'] ?? $parameters['tenant_id'] ?? '');
        $key = $rawKey === '' ? '' : hash('sha256', $tenantId . "\0" . $rawKey);
        if ($key !== '') {
            $signature = $this->actionSignature($task, $parameters);
            if (isset($this->completedByKey[$key])) {
                $prior = $this->completedByKey[$key];
                if (!hash_equals($prior['signature'], $signature)) {
                    return $this->record($executionId, $task, $parameters, 'conflict', ['reason' => 'Idempotency key was reused for different action data.']);
                }
                return $this->record($executionId, $task, $parameters, 'duplicate', [
                    'result' => $prior['result'],
                    'original_execution_id' => $prior['execution_id'],
                ]);
            }
        }

        try {
            $value = ($this->handlers[$task])($parameters);
            $result = $this->record($executionId, $task, $parameters, 'success', ['result' => $value]);
            if ($key !== '') {
                $this->completedByKey[$key] = [
                    'execution_id' => $executionId,
                    'signature' => $signature,
                    'result' => $value,
                ];
            }
            return $result;
        } catch (\Throwable $error) {
            return $this->record($executionId, $task, $parameters, 'failed', ['error' => $error->getMessage()]);
        }
    }

    public function executeBatch(array $tasks): array
    {
        $results = [];
        foreach ($tasks as $task) {
            if (!is_array($task) || !isset($task['name']) || !is_string($task['name'])) {
                $results[] = $this->record(bin2hex(random_bytes(12)), '', (array) $task, 'rejected', ['reason' => 'Batch entries require a task name.']);
                continue;
            }
            $results[] = $this->execute($task['name'], (array) ($task['parameters'] ?? []));
        }
        return $results;
    }

    public function getExecutionHistory(): array
    {
        return $this->history;
    }

    private function record(string $id, string $task, array $parameters, string $status, array $details): array
    {
        $entry = [
            'execution_id' => $id,
            'task' => $task,
            'parameters' => $parameters,
            'status' => $status,
            'completed_at' => gmdate(DATE_ATOM),
        ] + $details;
        $this->history[] = $entry;
        return $entry;
    }

    private function actionSignature(string $task, array $parameters): string
    {
        $tenant = (string) ($parameters['_context']['tenant_id'] ?? $parameters['tenant_id'] ?? '');
        unset($parameters['_approval'], $parameters['_context']);
        $canonical = $this->sortKeys(['task' => $task, 'tenant' => $tenant, 'parameters' => $parameters]);
        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function sortKeys(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeys($item);
            }
        }
        return $value;
    }
}
