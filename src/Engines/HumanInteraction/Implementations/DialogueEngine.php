<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Implementations;

use TitanZero\Engines\HumanInteraction\Contracts\DialogueEngineInterface;
class DialogueEngine implements DialogueEngineInterface
{
    private array $state = [];

    public function process(string $input): string
    {
        $this->state['last_input'] = $input;
        return "I understand. Let's continue.";
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function reset(): void
    {
        $this->state = [];
    }
}
