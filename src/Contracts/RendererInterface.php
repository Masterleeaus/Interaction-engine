<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

use TitanZero\Interaction\DTO\InteractionDefinition;

interface RendererInterface
{
    public function render(InteractionDefinition $definition, array $state, array $options = []): string;
}