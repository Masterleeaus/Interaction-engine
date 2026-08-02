<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Contracts;

interface StrategicPlanningEngineInterface
{
    public function defineStrategy(string $name, array $objectives): void;
    public function getActiveStrategies(): array;
    public function evaluateStrategy(string $name): array;
    public function updateStrategy(string $name, array $updates): void;
}
