<?php

declare(strict_types=1);

namespace TitanZero\Engines\Cognitive\Contracts;

interface CreativityEngineInterface
{
    public function generateIdeas(string $problem): array;
    public function combine(array $concepts): array;
    public function brainstorm(string $topic): array;
}
