<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

use TitanZero\Interaction\DTO\InteractionDefinition;

interface NavigationEngineInterface
{
    public function getNextSectionIndex(array $state, InteractionDefinition $definition): ?int;
    public function getPreviousSectionIndex(array $state, InteractionDefinition $definition): ?int;
}