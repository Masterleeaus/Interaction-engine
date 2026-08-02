<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Implementations;

use TitanZero\Engines\HumanInteraction\Contracts\ClarificationEngineInterface;
class ClarificationEngine implements ClarificationEngineInterface
{
    private array $history = [];

    public function ask(string $topic): string
    {
        $this->history[] = "Asked about: {$topic}";
        return "Could you clarify about {$topic}?";
    }

    public function requestMissing(array $required, array $provided): string
    {
        $missing = array_diff($required, array_keys($provided));
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
