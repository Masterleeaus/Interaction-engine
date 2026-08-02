<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard;

use TitanZero\Interaction\Contracts\CommandBusInterface;
use TitanZero\Interaction\Wizard\Command\CommandMapper;
use TitanZero\Interaction\Wizard\Guidance\LocalGuidanceProvider;
use TitanZero\Interaction\Wizard\Governance\GovernanceViolation;
use TitanZero\Interaction\Wizard\Governance\GovernedCompletionPolicy;
use TitanZero\Interaction\Wizard\Offline\LocalCommandOutbox;
use TitanZero\Interaction\Wizard\Validation\WizardValidationEngine;
use TitanZero\Interaction\Cognition\Events\CognitiveEvent;
use TitanZero\Interaction\Cognition\Events\CognitiveEventStoreInterface;
use TitanZero\Interaction\Cognition\Events\CognitiveEventType;

final class UniversalWizardEngine
{
    public function __construct(
        private readonly WizardRegistry $registry,
        private readonly WizardValidationEngine $validator,
        private readonly LocalGuidanceProvider $guidance,
        private readonly CommandMapper $commandMapper,
        private readonly LocalCommandOutbox $outbox,
        private readonly ?CommandBusInterface $commandBus = null,
        private readonly ?CognitiveEventStoreInterface $cognitiveEvents = null,
        private readonly ?GovernedCompletionPolicy $completionPolicy = null,
    ) {}

    public function start(string $wizardId, array $context = []): WizardSession
    {
        $session = new WizardSession(
            id: bin2hex(random_bytes(16)),
            definition: $this->registry->get($wizardId),
            context: $context,
        );
        $this->record(CognitiveEventType::ObservationRecorded, $session, ['wizard_started' => $wizardId]);
        return $session;
    }

    public function submitStep(WizardSession $session, array $input): WizardResult
    {
        if ($session->complete()) {
            return new WizardResult($session, true, guidance: 'This wizard is already complete.');
        }
        $step = $session->currentStep();
        if ($step === null) {
            throw new \LogicException('Wizard session points to a missing step.');
        }
        $errors = $this->validator->validateStep($step, $input, $session->data);
        if ($errors !== []) {
            return new WizardResult($session, errors: $errors, guidance: $this->guidance->guidance($step, $session->data, $errors));
        }

        $snapshot = [
            'data' => $session->data,
            'history' => $session->history,
            'step_index' => $session->stepIndex,
            'status' => $session->status,
        ];

        $session->data = array_replace($session->data, $input);
        $session->history[] = [
            'step_id' => $step['id'],
            'input' => $input,
            'completed_at' => gmdate(DATE_ATOM),
        ];
        $session->stepIndex++;
        $this->skipConditionalSteps($session);

        if ($session->stepIndex >= $session->definition->stepCount()) {
            try {
                ($this->completionPolicy ?? new GovernedCompletionPolicy())->assertMayComplete(
                    $session->definition,
                    $session->data,
                    $session->context,
                );
            } catch (GovernanceViolation $violation) {
                $session->data = $snapshot['data'];
                $session->history = $snapshot['history'];
                $session->stepIndex = $snapshot['step_index'];
                $session->status = $snapshot['status'];
                return new WizardResult(
                    $session,
                    errors: ['_governance' => $violation->violations],
                    guidance: 'This workflow cannot complete until its governance requirements are satisfied.',
                );
            }

            $session->status = 'completed';
            $command = $this->commandMapper->map($session);
            $metadata = (array) ($command['metadata'] ?? []);
            $context = $session->context;
            $command['payload']['_context'] = array_replace((array) ($command['payload']['_context'] ?? []), [
                'tenant_id' => (string) ($context['tenant_id'] ?? ''),
                'user_id' => $context['user_id'] ?? null,
                'device_id' => (string) ($context['device_id'] ?? ''),
                'team_id' => $context['team_id'] ?? null,
                'actor_type' => (string) ($context['actor_type'] ?? 'unknown'),
                'roles' => array_values((array) ($context['roles'] ?? [])),
                'delegated_scopes' => array_values((array) ($context['delegated_scopes'] ?? [])),
                'authenticated_at' => $context['authenticated_at'] ?? null,
                'wizard_run_id' => $session->id,
                'correlation_id' => (string) ($metadata['correlation_id'] ?? $context['correlation_id'] ?? $session->id),
                'causation_id' => (string) ($metadata['causation_id'] ?? $context['causation_id'] ?? $context['correlation_id'] ?? $session->id),
                'idempotency_key' => (string) ($metadata['idempotency_key'] ?? ''),
                'template_id' => (string) ($metadata['template_id'] ?? $session->definition->id),
                'template_version' => (string) ($metadata['template_version'] ?? $session->definition->version),
                'privacy_class' => (string) ($context['privacy_class'] ?? 'tenant_private'),
            ]);
            $this->record(CognitiveEventType::CommandPrepared, $session, ['command' => $command]);
            if ($this->commandBus !== null) {
                $this->commandBus->dispatch((string) $command['capability'], (array) $command['payload']);
                return new WizardResult($session, true, command: $command, guidance: 'Executed through the WorkCore command boundary.');
            }
            $this->outbox->enqueue($command);
            return new WizardResult($session, true, command: $command, guidance: 'Saved locally and queued for execution.');
        }

        $next = $session->currentStep() ?? [];
        return new WizardResult($session, guidance: $this->guidance->guidance($next, $session->data));
    }

    private function skipConditionalSteps(WizardSession $session): void
    {
        while (($step = $session->currentStep()) !== null && isset($step['when']) && !$this->matches((array) $step['when'], $session->data)) {
            $session->history[] = ['step_id' => $step['id'], 'skipped' => true, 'completed_at' => gmdate(DATE_ATOM)];
            $session->stepIndex++;
        }
    }

    private function matches(array $conditions, array $data): bool
    {
        foreach ($conditions as $key => $expected) {
            if (($data[$key] ?? null) !== $expected) {
                return false;
            }
        }
        return true;
    }

    private function record(CognitiveEventType $type, WizardSession $session, array $payload): void
    {
        if ($this->cognitiveEvents === null) {
            return;
        }
        $tenantId = (string) ($session->context['tenant_id'] ?? 'default');
        $event = CognitiveEvent::create(
            type: $type,
            tenantId: $tenantId,
            payload: $payload,
            userId: isset($session->context['user_id']) ? (string) $session->context['user_id'] : null,
            deviceId: isset($session->context['device_id']) ? (string) $session->context['device_id'] : null,
            teamId: isset($session->context['team_id']) ? (string) $session->context['team_id'] : null,
            wizardRunId: $session->id,
            correlationId: (string) ($session->context['correlation_id'] ?? $session->id),
            privacyClass: (string) ($session->context['privacy_class'] ?? 'tenant_private'),
            sequence: count($session->history),
        );
        $this->cognitiveEvents->append($event);
    }
}
