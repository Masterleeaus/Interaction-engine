<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Implementations;

use TitanZero\Engines\AIInfrastructure\Contracts\GuardrailEngineInterface;
class GuardrailEngine implements GuardrailEngineInterface
{
    private array $guardrails = [
        'email_address' => ['pattern' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', 'contexts' => []],
        'phone_number' => ['pattern' => '/\b(?:\+?61|0)(?:[\s().-]*\d){8,12}\b/', 'contexts' => []],
        'instruction_override' => ['pattern' => '/\bignore\s+(?:all\s+)?(?:previous|prior)\s+instructions\b/i', 'contexts' => ['model_input']],
    ];

    public function validate(string $input, string $context): bool
    {
        foreach ($this->guardrails as $rule) {
            if ($rule['contexts'] !== [] && !in_array($context, $rule['contexts'], true)) {
                continue;
            }
            if (preg_match($rule['pattern'], $input) === 1) {
                return false;
            }
        }
        return true;
    }

    public function enforce(string $input): string
    {
        foreach ($this->guardrails as $rule) {
            $input = preg_replace($rule['pattern'], '[REDACTED]', $input) ?? $input;
        }
        return $input;
    }

    public function listGuardrails(): array
    {
        return array_keys($this->guardrails);
    }

    public function registerGuardrail(string $name, string $pattern, array $contexts = []): void
    {
        if (trim($name) === '' || @preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException('Guardrail name and a valid PCRE pattern are required.');
        }
        $this->guardrails[$name] = ['pattern' => $pattern, 'contexts' => array_values(array_map('strval', $contexts))];
    }

    public function removeGuardrail(string $name): void
    {
        unset($this->guardrails[$name]);
    }
}
