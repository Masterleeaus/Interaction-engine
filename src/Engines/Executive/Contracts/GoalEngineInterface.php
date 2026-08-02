<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Contracts;

interface GoalEngineInterface
{
    public function setGoal(string $name, string $description, array $targets = []): void;
    public function updateProgress(string $name, float $progress): void;
    public function getGoals(): array;
    public function getNextMilestones(): array;
    public function getOverallProgress(): float;
}
