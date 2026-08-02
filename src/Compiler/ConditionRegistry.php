<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Compiler;

class ConditionRegistry
{
    private array $conditions = [];

    public function register(string $name, callable $evaluator): void
    {
        $this->conditions[$name] = $evaluator;
    }

    public function evaluate(string $name, array $context): bool
    {
        if (!isset($this->conditions[$name])) {
            throw new \RuntimeException("Condition '{$name}' not found.");
        }
        return call_user_func($this->conditions[$name], $context);
    }

    public function expand(array $definition): array
    {
        // Pass through; conditions are evaluated at runtime
        return $definition;
    }
}