<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

use TitanZero\Interaction\DTO\InteractionDefinition;

interface GeneratorInterface
{
    public function generate(array $requirements): InteractionDefinition;
    public function suggestInteractions(int $userId, array $context): array;
}
