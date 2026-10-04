<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\WorkflowEngineInterface;

class WorkflowEngine implements WorkflowEngineInterface
{
    private array $workflows = [];
    private array $executions = [];
    private ?string $latestExecutionId = null;

    public function __construct(private readonly \TitanZero\Engines\Planning\Contracts\ExecutionEngineInterface $executor) {}

    public function registerWorkflow(string $name, array $steps): void
    {
        if (trim($name) === '' || $steps === []) {
            throw new \InvalidArgumentException('Workflow name and at least one step are required.');
        }
        foreach ($steps as $index => $step) {
            if (!is_array($step) || trim((string) ($step['action'] ?? $step['name'] ?? '')) === '') {
                throw new \InvalidArgumentException("Workflow step {$index} must name an action.");
            }
            if (isset($step['parameters']) && !is_array($step['parameters'])) {
                throw new \InvalidArgumentException("Workflow step {$index} parameters must be an object.");
            }
        }
        $this->workflows[$name] = $steps;
    }

    public function startWorkflow(string $name, array $input): string
    {
        if (!isset($this->workflows[$name])) {
            throw new \InvalidArgumentException("Workflow '{$name}' is not registered.");
        }
        $executionId = 'wf_' . bin2hex(random_bytes(12));
        $this->latestExecutionId = $executionId;
        $this->executions[$executionId] = [
            'id' => $executionId,
            'workflow' => $name,
            'input' => $input,
            'status' => 'running',
            'steps' => [],
            'started_at' => gmdate(DATE_ATOM),
        ];
        $trustedContext = (array) ($input['_context'] ?? []);
        $workflowKey = (string) ($input['_idempotency_key'] ?? $input['idempotency_key'] ?? '');
        foreach ($this->workflows[$name] as $index => $step) {
            $action = (string) ($step['action'] ?? $step['name']);
            $stepParameters = (array) ($step['parameters'] ?? []);
            $parameters = array_replace($input, $stepParameters);
            $parameters['_context'] = $trustedContext;
            $stepKey = (string) ($stepParameters['_idempotency_key'] ?? $stepParameters['idempotency_key'] ?? $workflowKey);
            unset($parameters['_idempotency_key'], $parameters['idempotency_key']);
            if ($stepKey !== '') {
                $parameters['_idempotency_key'] = hash('sha256', $stepKey . '|' . $name . '|' . $index);
            }
            $result = $this->executor->execute($action, $parameters);
            $this->executions[$executionId]['steps'][] = [
                'index' => $index,
                'action' => $action,
                'result' => $result,
            ];
            if (!in_array($result['status'] ?? null, ['success', 'duplicate'], true)) {
                $this->executions[$executionId]['status'] = 'failed';
                $this->executions[$executionId]['failed_step'] = $index;
                $this->executions[$executionId]['completed_at'] = gmdate(DATE_ATOM);
                return $executionId;
            }
        }
        $this->executions[$executionId]['status'] = 'completed';
        $this->executions[$executionId]['completed_at'] = gmdate(DATE_ATOM);
        return $executionId;
    }

    public function getStatus(string $executionId): array
    {
        return $this->executions[$executionId] ?? ['status' => 'not_found'];
    }

    public function listWorkflows(): array
    {
        return array_keys($this->workflows);
    }

    public function getLatestExecutionId(): ?string
    {
        return $this->latestExecutionId;
    }
}
