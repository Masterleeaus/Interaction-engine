<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

interface ContextProviderInterface
{
    public function provide(array $state): array;
}