<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

interface EventRecorderInterface
{
    public function record(string $eventType, array $data): void;
    public function getEvents(int $runId): array;
}