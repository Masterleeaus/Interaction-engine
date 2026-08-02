<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Events;

final readonly class InteractionEventRecorded
{
    public function __construct(
        public string $eventType,
        public array $data,
    ) {}
}
