<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Repositories;

use TitanZero\Interaction\DTO\Answer;

interface InteractionAnswerRepositoryInterface
{
    public function save(int $runId, Answer $answer): void;
    public function getAll(int $runId): array;
}