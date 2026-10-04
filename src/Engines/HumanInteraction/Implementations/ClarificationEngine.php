<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Implementations;

use TitanZero\Engines\HumanInteraction\Contracts\ClarificationEngineInterface;
class ClarificationEngine implements ClarificationEngineInterface
{
    private array $history = [];

    public function ask(string $topic): string
    {
        $topic = trim($topic);
        if ($topic === '') {
            throw new \InvalidArgumentException('Clarification topic is required.');
        }
        $this->history[] = "Asked about: {$topic}";
        return "Could you clarify about {$topic}?";
    }

    public function requestMissing(array $required, array $provided): string
    {
        $missing = array_values(array_filter($required, function (mixed $field) use ($provided): bool {
            if (!is_string($field) || trim($field) === '') {
                throw new \InvalidArgumentException('Required fields must be non-empty names.');
            }
            $value = $provided;
            foreach (explode('.', $field) as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return true;
                }
                $value = $value[$segment];
            }
            return $value === null || $value === '' || $value === [];
        }));
        if (empty($missing)) {
            return 'All required information provided.';
        }
        $this->history[] = 'Missing: ' . implode(', ', $missing);
        return 'I still need: ' . implode(', ', $missing);
    }

    public function getClarificationHistory(): array
    {
        return $this->history;
    }
}
