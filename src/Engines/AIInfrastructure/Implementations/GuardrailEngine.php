<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Implementations;

use TitanZero\Engines\AIInfrastructure\Contracts\GuardrailEngineInterface;
class GuardrailEngine implements GuardrailEngineInterface
{
    private array $guardrails = [
        'profanity' => '/\b(badword)\b/i',
        'sensitive' => '/\b(personal_info)\b/i',
    ];

    public function validate(string $input, string $context): bool
    {
        foreach ($this->guardrails as $pattern) {
            if (preg_match($pattern, $input)) {
                return false;
            }
        }
        return true;
    }

    public function enforce(string $input): string
    {
        foreach ($this->guardrails as $pattern) {
            $input = preg_replace($pattern, '[REDACTED]', $input);
        }
        return $input;
    }

    public function listGuardrails(): array
    {
        return array_keys($this->guardrails);
    }
}
