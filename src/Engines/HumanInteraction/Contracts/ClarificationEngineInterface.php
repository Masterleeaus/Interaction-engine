<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Contracts;

interface ClarificationEngineInterface
{
    public function ask(string $topic): string;
    public function requestMissing(array $required, array $provided): string;
    public function getClarificationHistory(): array;
}
