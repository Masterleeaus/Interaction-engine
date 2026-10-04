<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Implementations;

use TitanZero\Engines\AIInfrastructure\Contracts\FunctionCallingEngineInterface;
use TitanZero\Interaction\Contracts\PolicyEngineInterface;

class FunctionCallingEngine implements FunctionCallingEngineInterface
{
    private array $functions = [];

    public function __construct(private readonly PolicyEngineInterface $policyEngine) {}

    public function call(string $function, array $parameters): mixed
    {
        if (!isset($this->functions[$function])) {
            throw new \RuntimeException("Function '{$function}' not found");
        }
        $decision = $this->policyEngine->decide($function, $parameters);
        if (!$decision->allowed) {
            throw new \RuntimeException('Function call denied: ' . implode('; ', $decision->reasons));
        }
        return ($this->functions[$function])($parameters);
    }

    public function registerFunction(string $name, callable $function): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Function capability name is required.');
        }
        $this->functions[$name] = $function;
    }

    public function listFunctions(): array
    {
        return array_keys($this->functions);
    }
}
