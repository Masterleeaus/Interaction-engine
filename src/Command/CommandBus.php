<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Command;

use TitanZero\Interaction\Contracts\CommandBusInterface;
use TitanZero\Interaction\Contracts\PolicyEngineInterface;
use TitanZero\Interaction\Contracts\EventRecorderInterface;
use TitanZero\Interaction\Cognition\Events\CognitiveEvent;
use TitanZero\Interaction\Cognition\Events\CognitiveEventStoreInterface;
use TitanZero\Interaction\Cognition\Events\CognitiveEventType;

class CommandBus implements CommandBusInterface
{
    private array $handlers = [];
    private PolicyEngineInterface $policyEngine;
    private EventRecorderInterface $eventRecorder;

    public function __construct(
        PolicyEngineInterface $policyEngine,
        EventRecorderInterface $eventRecorder,
        private readonly ?CognitiveEventStoreInterface $cognitiveEvents = null,
    ) {
        $this->policyEngine = $policyEngine;
        $this->eventRecorder = $eventRecorder;
    }

    public function registerHandler(string $capability, callable $handler): void
    {
        $this->handlers[$capability] = $handler;
    }

    public function hasHandler(string $capability): bool
    {
        return isset($this->handlers[$capability]);
    }

    public function dispatch(string $capability, array $payload): void
    {
        if (!$this->hasHandler($capability)) {
            throw new \RuntimeException("No handler registered for capability '{$capability}'.");
        }

        $tenantId = (string) ($payload['tenant_id'] ?? $payload['_context']['tenant_id'] ?? 'default');
        $correlationId = (string) ($payload['correlation_id'] ?? $payload['_context']['correlation_id'] ?? '');
        $this->recordCognitive(CognitiveEventType::CommandPrepared, $tenantId, $capability, $payload, $correlationId);

        // Evaluate immutable authority and policy rules. Unknown capabilities fail closed.
        $decision = $this->policyEngine->decide($capability, $payload);
        if (!$decision->allowed) {
            $reasons = $decision->reasons;
            $this->eventRecorder->record('capability_denied', [
                'capability' => $capability,
                'payload' => $payload,
                'reasons' => $reasons,
            ]);
            $this->recordCognitive(CognitiveEventType::ConstraintRejected, $tenantId, $capability, ['reasons' => $reasons, 'authority' => $decision->authority->value, 'requires_human' => $decision->requiresHuman, 'requires_approval' => $decision->requiresApproval], $correlationId);
            throw new \RuntimeException("Policy denied: " . implode('; ', $reasons));
        }

        try {
            $handler = $this->handlers[$capability];
            $result = $handler($payload);

            $this->eventRecorder->record('capability_executed', [
                'capability' => $capability,
                'payload' => $payload,
                'result' => $result,
                'success' => true,
            ]);
            $this->recordCognitive(CognitiveEventType::CommandExecuted, $tenantId, $capability, ['result' => $result, 'success' => true], $correlationId);

        } catch (\Throwable $e) {
            $this->eventRecorder->record('capability_executed', [
                'capability' => $capability,
                'payload' => $payload,
                'error' => $e->getMessage(),
                'success' => false,
            ]);
            $this->recordCognitive(CognitiveEventType::CommandFailed, $tenantId, $capability, ['error' => $e->getMessage(), 'success' => false], $correlationId);
            throw $e;
        }
    }

    private function recordCognitive(CognitiveEventType $type, string $tenantId, string $capability, array $payload, string $correlationId): void
    {
        if ($this->cognitiveEvents === null) {
            return;
        }
        $context = (array) ($payload['_context'] ?? []);
        $event = CognitiveEvent::create(
            type: $type,
            tenantId: $tenantId,
            payload: ['capability' => $capability, 'details' => $payload],
            userId: isset($context['user_id']) ? (string) $context['user_id'] : null,
            deviceId: isset($context['device_id']) ? (string) $context['device_id'] : null,
            teamId: isset($context['team_id']) ? (string) $context['team_id'] : null,
            subjectType: isset($context['subject_type']) ? (string) $context['subject_type'] : null,
            subjectId: isset($context['subject_id']) ? (string) $context['subject_id'] : null,
            wizardRunId: isset($context['wizard_run_id']) ? (string) $context['wizard_run_id'] : null,
            correlationId: $correlationId !== '' ? $correlationId : null,
            privacyClass: (string) ($context['privacy_class'] ?? 'tenant_private'),
        );
        $this->cognitiveEvents->append($event);
    }
}