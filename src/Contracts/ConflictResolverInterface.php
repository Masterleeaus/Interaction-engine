<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

interface ConflictResolverInterface
{
    public function resolve(array $command): array;
    public function resolveConflicts(array $commands): array;
}