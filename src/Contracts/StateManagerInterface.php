<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

use TitanZero\Interaction\DTO\InteractionDefinition;
use TitanZero\Interaction\DTO\AnswerCollection;
use TitanZero\Interaction\DTO\Section;

interface StateManagerInterface
{
    public function start(string $interactionId, int $userId): array;
    public function load(int $runId): array;
    public function save(array $state): void;
    public function getCurrentSection(array $state, InteractionDefinition $definition): ?Section;
    public function getUnansweredQuestions(array $state, InteractionDefinition $definition): array;
    public function updateAnswers(array $state, AnswerCollection $answers): array;
    public function setCompleted(array $state): array;
}
