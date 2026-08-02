<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface PlanningEngineInterface
{
    public function generatePlan(string $goal, array $context): array;
    public function decomposePlan(array $plan): array;
    public function validatePlan(array $plan): bool;
    public function getActivePlans(): array;
}
