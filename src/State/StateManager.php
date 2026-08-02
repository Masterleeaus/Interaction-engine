<?php

declare(strict_types=1);

namespace TitanZero\Interaction\State;

use TitanZero\Interaction\Contracts\StateManagerInterface;
use TitanZero\Interaction\DTO\AnswerCollection;
use TitanZero\Interaction\DTO\InteractionDefinition;
use TitanZero\Interaction\DTO\Section;
use TitanZero\Interaction\Repositories\InteractionAnswerRepositoryInterface;
use TitanZero\Interaction\Repositories\InteractionRunRepositoryInterface;

class StateManager implements StateManagerInterface
{
    public function __construct(
        private readonly InteractionRunRepositoryInterface $runRepository,
        private readonly InteractionAnswerRepositoryInterface $answerRepository,
    ) {}

    public function start(string $interactionId, int $userId): array
    {
        $runId = $this->runRepository->create($userId, $interactionId, '1.0.0');
        return $this->load($runId);
    }

    public function load(int $runId): array
    {
        $run = $this->runRepository->find($runId);
        if (!$run) {
            throw new \RuntimeException("Run {$runId} not found.");
        }
        $run['answers'] = $this->answerRepository->getAll($runId);
        return $run;
    }

    public function save(array $state): void
    {
        $this->runRepository->update((int) $state['id'], [
            'current_section_index' => (int) ($state['current_section_index'] ?? 0),
            'state' => $state['state'] ?? 'in_progress',
            'meta' => $state['meta'] ?? [],
            'completed_at' => $state['completed_at'] ?? null,
        ]);
    }

    public function getCurrentSection(array $state, InteractionDefinition $definition): ?Section
    {
        return $definition->sections[(int) ($state['current_section_index'] ?? 0)] ?? null;
    }

    public function getUnansweredQuestions(array $state, InteractionDefinition $definition): array
    {
        $section = $this->getCurrentSection($state, $definition);
        if (!$section) {
            return [];
        }
        $answered = [];
        foreach ($state['answers'] ?? [] as $answer) {
            if (is_object($answer) && isset($answer->questionKey)) {
                $answered[$answer->questionKey] = $answer->value;
            }
        }
        return array_values(array_filter($section->questions, static fn($question): bool => !array_key_exists($question->key, $answered)));
    }

    public function updateAnswers(array $state, AnswerCollection $answers): array
    {
        foreach ($answers->all() as $answer) {
            $this->answerRepository->save((int) $state['id'], $answer);
        }
        $state['answers'] = $this->answerRepository->getAll((int) $state['id']);
        $state['state'] = 'in_progress';
        return $state;
    }

    public function setCompleted(array $state): array
    {
        $state['state'] = 'completed';
        $state['completed_at'] = date('Y-m-d H:i:s');
        return $state;
    }
}
