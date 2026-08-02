<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Events;

class SectionCompleted
{
    public function __construct(
        public int $runId,
        public string $sectionId,
        public int $sectionIndex,
    ) {}
}