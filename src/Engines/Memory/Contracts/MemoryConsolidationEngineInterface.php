<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Contracts;

interface MemoryConsolidationEngineInterface
{
    public function consolidate(array $events): void;
    public function summarize(array $events): array;
    public function prioritizeForConsolidation(): array;
}
