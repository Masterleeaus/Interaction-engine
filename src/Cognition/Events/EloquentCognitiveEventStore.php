<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Cognition\Events;

use TitanZero\Interaction\Models\CognitiveEvent as CognitiveEventModel;

final class EloquentCognitiveEventStore implements CognitiveEventStoreInterface
{
    public function append(CognitiveEvent $event): void
    {
        CognitiveEventModel::query()->firstOrCreate(
            ['tenant_id' => $event->tenantId, 'event_id' => $event->eventId],
            $event->toArray(),
        );
    }

    public function find(string $tenantId, string $eventId): ?CognitiveEvent
    {
        $row = CognitiveEventModel::query()->where('tenant_id', $tenantId)->where('event_id', $eventId)->first();
        return $row === null ? null : CognitiveEvent::fromArray($this->normalise($row->toArray()));
    }

    public function forCorrelation(string $tenantId, string $correlationId): array
    {
        return CognitiveEventModel::query()
            ->where('tenant_id', $tenantId)
            ->where('correlation_id', $correlationId)
            ->orderBy('sequence')
            ->orderBy('occurred_at')
            ->orderBy('event_id')
            ->get()
            ->map(fn (CognitiveEventModel $row): CognitiveEvent => CognitiveEvent::fromArray($this->normalise($row->toArray())))
            ->all();
    }

    private function normalise(array $data): array
    {
        foreach (['occurred_at', 'recorded_at'] as $field) {
            if (isset($data[$field]) && is_object($data[$field]) && method_exists($data[$field], 'toAtomString')) {
                $data[$field] = $data[$field]->toAtomString();
            }
        }
        return $data;
    }
}
