<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Runtime;

use TitanZero\Interaction\Context\ContextBuilder;
use TitanZero\Interaction\Contracts\CommandBusInterface;
use TitanZero\Interaction\Contracts\EventRecorderInterface;
use TitanZero\Interaction\Contracts\NavigationEngineInterface;
use TitanZero\Interaction\Contracts\OfflineQueueInterface;
use TitanZero\Interaction\Contracts\QuestionResolverInterface;
use TitanZero\Interaction\Contracts\RendererInterface;
use TitanZero\Interaction\Contracts\StateManagerInterface;
use TitanZero\Interaction\Contracts\ValidationEngineInterface;
use TitanZero\Interaction\DTO\Answer;
use TitanZero\Interaction\DTO\AnswerCollection;
use TitanZero\Interaction\DTO\InteractionDefinition;
use TitanZero\Interaction\Offline\OfflineDetector;
use TitanZero\Interaction\Registry\InteractionRegistry;

class InteractionRuntime
{
    public function __construct(
        private readonly InteractionRegistry $registry,
        private readonly StateManagerInterface $stateManager,
        private readonly NavigationEngineInterface $navigationEngine,
        private readonly ValidationEngineInterface $validationEngine,
        private readonly RendererInterface $renderer,
        private readonly QuestionResolverInterface $questionResolver,
        private readonly ContextBuilder $contextBuilder,
        private readonly EventRecorderInterface $eventRecorder,
        private readonly CommandBusInterface $commandBus,
        private readonly OfflineDetector $offlineDetector,
        private readonly OfflineQueueInterface $offlineQueue,
    ) {}

    public function start(string $interactionId, int $userId): array
    {
        $definition = $this->registry->get($interactionId);
        if ($definition === null) {
            throw new \RuntimeException("Interaction '{$interactionId}' not found.");
        }
        $state = $this->stateManager->start($interactionId, $userId);
        $state['definition'] = $definition;
        $this->eventRecorder->record('interaction_started', [
            'run_id' => $state['id'],
            'user_id' => $userId,
            'interaction_id' => $interactionId,
            'definition_version' => $definition->version,
        ]);
        return $state;
    }

    public function load(int $runId): array
    {
        $state = $this->stateManager->load($runId);
        $state['definition'] = $this->registry->get((string) $state['interaction_id']);
        return $state;
    }

    public function getCurrentView(int $runId): string
    {
        $state = $this->load($runId);
        return $this->renderer->render($state['definition'], $state);
    }

    public function process(array $state, array $input): array
    {
        /** @var InteractionDefinition $definition */
        $definition = $state['definition'];
        $action = (string) ($input['action'] ?? 'next');
        $answers = is_array($input['answers'] ?? null) ? $input['answers'] : [];
        $section = $this->stateManager->getCurrentSection($state, $definition);

        if ($action === 'back') {
            $previous = $this->navigationEngine->getPreviousSectionIndex($state, $definition);
            if ($previous !== null) {
                $state['current_section_index'] = $previous;
                $this->stateManager->save($state);
            }
            return ['state' => $state];
        }

        if ($section && $answers === []) {
            $state = $this->autoResolve($state, $section->questions);
        }

        if ($section) {
            $allValues = $this->answerMap($state);
            $allValues = array_replace($allValues, $answers);
            $errors = [];
            foreach ($section->questions as $question) {
                $fieldErrors = $this->validationEngine->validate($question, $allValues[$question->key] ?? null);
                if ($fieldErrors !== []) {
                    $errors[$question->key] = $fieldErrors;
                }
            }
            if ($errors !== []) {
                return ['errors' => $errors, 'state' => $state];
            }
        }

        if ($answers !== []) {
            $objects = [];
            foreach ($answers as $key => $value) {
                $objects[] = new Answer((string) $key, $value, 'user', 1.0, new \DateTimeImmutable(), true);
                $this->eventRecorder->record('question_answered', [
                    'run_id' => $state['id'],
                    'question_key' => (string) $key,
                    'source' => 'user',
                ]);
            }
            $state = $this->stateManager->updateAnswers($state, new AnswerCollection(...$objects));
        }

        $next = $this->navigationEngine->getNextSectionIndex($state, $definition);
        if ($next === null || $action === 'submit') {
            $state = $this->stateManager->setCompleted($state);
            $this->stateManager->save($state);
            $payload = $this->buildPayload($state);
            $this->executeOrQueue($definition->capability, $payload, $state);
            $this->eventRecorder->record('interaction_completed', [
                'run_id' => $state['id'],
                'interaction_id' => $definition->id,
                'capability' => $definition->capability,
            ]);
            return ['complete' => true, 'state' => $state];
        }

        if ((int) $state['current_section_index'] !== $next && $section) {
            $this->eventRecorder->record('section_completed', [
                'run_id' => $state['id'],
                'section_id' => $section->id,
                'section_index' => $state['current_section_index'],
            ]);
        }
        $state['current_section_index'] = $next;
        $this->stateManager->save($state);
        return ['state' => $state];
    }

    private function autoResolve(array $state, array $questions): array
    {
        $context = $this->contextBuilder->build($state);
        $resolved = [];
        foreach ($questions as $question) {
            $answer = $this->questionResolver->resolve($question, $context);
            if ($answer->value !== null && $answer->source !== 'user') {
                $resolved[] = $answer;
                $this->eventRecorder->record('question_answered', [
                    'run_id' => $state['id'],
                    'question_key' => $question->key,
                    'source' => $answer->source,
                    'confidence' => $answer->confidence,
                ]);
            }
        }
        return $resolved === [] ? $state : $this->stateManager->updateAnswers($state, new AnswerCollection(...$resolved));
    }

    private function executeOrQueue(string $capability, array $payload, array $state): void
    {
        if (config('interaction.offline.enabled', true) && $this->offlineDetector->isOffline()) {
            $this->offlineQueue->queue($capability, $payload, [
                'run_id' => $state['id'],
                'tenant_id' => $state['tenant_id'] ?? null,
                'user_id' => $state['user_id'],
                'device_id' => $state['device_id'] ?? null,
                'queued_at' => gmdate(DATE_ATOM),
            ]);
            return;
        }
        $this->commandBus->dispatch($capability, $payload);
    }

    private function answerMap(array $state): array
    {
        $map = [];
        foreach ($state['answers'] ?? [] as $answer) {
            if (is_object($answer) && isset($answer->questionKey)) {
                $map[$answer->questionKey] = $answer->value;
            }
        }
        return $map;
    }

    private function buildPayload(array $state): array
    {
        $payload = $this->answerMap($state);
        $payload['interaction_id'] = $state['interaction_id'];
        $payload['run_id'] = $state['id'];
        return $payload;
    }
}
