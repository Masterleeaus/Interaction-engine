<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Cognition\Events;

final class InMemoryCognitiveEventStore implements CognitiveEventStoreInterface
{
    /** @var array<string,CognitiveEvent> */
    private array $events = [];

    public function append(CognitiveEvent $event): void
    {
        $key = $event->tenantId . ':' . $event->eventId;
        $this->events[$key] ??= $event;
    }

    public function find(string $tenantId, string $eventId): ?CognitiveEvent
    {
        return $this->events[$tenantId . ':' . $eventId] ?? null;
    }

    public function forCorrelation(string $tenantId, string $correlationId): array
    {
        $events = array_values(array_filter(
            $this->events,
            static fn (CognitiveEvent $event): bool => $event->tenantId === $tenantId && $event->correlationId === $correlationId,
        ));
        usort($events, static fn (CognitiveEvent $a, CognitiveEvent $b): int => [$a->sequence, $a->occurredAt, $a->eventId] <=> [$b->sequence, $b->occurredAt, $b->eventId]);
        return $events;
    }
}
