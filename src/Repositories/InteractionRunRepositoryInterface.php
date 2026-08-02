<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Repositories;

interface InteractionRunRepositoryInterface
{
    public function create(int $userId, string $interactionId, string $definitionVersion): int;
    public function find(int $runId): ?array;
    public function update(int $runId, array $data): void;
    public function findActiveForUser(int $userId, string $interactionId): ?array;
}