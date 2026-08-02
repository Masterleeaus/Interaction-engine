<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Cognition\Events;

interface CognitiveEventStoreInterface
{
    public function append(CognitiveEvent $event): void;
    public function find(string $tenantId, string $eventId): ?CognitiveEvent;
    /** @return list<CognitiveEvent> */
    public function forCorrelation(string $tenantId, string $correlationId): array;
}
