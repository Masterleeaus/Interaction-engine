<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\AutomationEngineInterface;
use TitanZero\Engines\Planning\Contracts\ExecutionEngineInterface;

class AutomationEngine implements AutomationEngineInterface
{
    private array $automations = [];
    private array $executionContexts = [];

    public function __construct(private readonly ExecutionEngineInterface $executor) {}

    public function registerTaskHandler(string $task, callable $handler): void
    {
        if (trim($task) === '') {
            throw new \InvalidArgumentException('Automation task name is required.');
        }
        $this->executor->registerHandler($task, $handler);
    }

    public function automate(string $task, array $schedule): void
    {
        if (trim($task) === '') {
            throw new \InvalidArgumentException('Automation task name is required.');
        }
        if (isset($schedule['parameters']) && !is_array($schedule['parameters'])) {
            throw new \InvalidArgumentException('Automation parameters must be an object.');
        }
        if (isset($schedule['trusted_context']) && !is_array($schedule['trusted_context'])) {
            throw new \InvalidArgumentException('Automation trusted_context must be an object.');
        }
        $trustedContext = (array) ($schedule['trusted_context'] ?? []);
        unset($schedule['trusted_context']);
        $id = 'automation_' . bin2hex(random_bytes(8));
        $this->executionContexts[$id] = $trustedContext;
        $this->automations[$id] = [
            'id' => $id,
            'task' => $task,
            'schedule' => $schedule,
            'enabled' => ($schedule['enabled'] ?? true) === true,
            'status' => 'ready',
            'run_count' => 0,
            'created_at' => gmdate(DATE_ATOM),
        ];
    }

    public function getAutomations(): array
    {
        return array_values($this->automations);
    }

    public function trigger(string $automationId): void
    {
        if (!isset($this->automations[$automationId])) {
            throw new \InvalidArgumentException("Automation '{$automationId}' was not found.");
        }
        $automation = $this->automations[$automationId];
        if (!$automation['enabled']) {
            throw new \LogicException("Automation '{$automationId}' is disabled.");
        }
        $this->automations[$automationId]['status'] = 'running';
        $this->automations[$automationId]['last_started_at'] = gmdate(DATE_ATOM);
        try {
            $parameters = (array) ($automation['schedule']['parameters'] ?? []);
            unset($parameters['_context']);
            $parameters['automation_id'] = $automationId;
            $parameters['_context'] = $this->executionContexts[$automationId] ?? [];
            $result = $this->executor->execute($automation['task'], $parameters);
            $resultStatus = (string) ($result['status'] ?? 'failed');
            if (!in_array($resultStatus, ['success', 'duplicate'], true)) {
                $this->automations[$automationId]['status'] = $resultStatus === 'denied' ? 'rejected' : 'failed';
                $this->automations[$automationId]['last_result'] = $result;
                $this->automations[$automationId]['last_error'] = $result['reasons'][0] ?? $result['reason'] ?? 'The execution engine did not complete the action.';
                $this->automations[$automationId]['last_failed_at'] = gmdate(DATE_ATOM);
                throw new \RuntimeException('Automation action was not executed: ' . $this->automations[$automationId]['last_error']);
            }
            $this->automations[$automationId]['status'] = 'completed';
            $this->automations[$automationId]['last_result'] = $result;
            $this->automations[$automationId]['run_count']++;
            $this->automations[$automationId]['last_completed_at'] = gmdate(DATE_ATOM);
        } catch (\Throwable $error) {
            if ($this->automations[$automationId]['status'] === 'running') {
                $this->automations[$automationId]['status'] = 'failed';
                $this->automations[$automationId]['last_error'] = $error->getMessage();
                $this->automations[$automationId]['last_failed_at'] = gmdate(DATE_ATOM);
            }
            throw $error;
        }
    }
}
