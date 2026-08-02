<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Contracts;

interface DialogueEngineInterface
{
    public function process(string $input): string;
    public function getState(): array;
    public function reset(): void;
}
