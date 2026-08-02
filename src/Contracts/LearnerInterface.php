<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

interface LearnerInterface
{
    public function learn(int $userId, array $event): void;
    public function getPatterns(int $userId): array;
    public function getSuggestions(int $userId, array $context): array;
}