<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Contracts;

interface ReinforcementLearningEngineInterface
{
    public function getAction(array $state): string;
    public function updateQValue(array $state, string $action, float $reward): void;
    public function getPolicy(): array;
}
