<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Event;

use TitanZero\Interaction\Contracts\EventRecorderInterface;
use TitanZero\Interaction\Events\InteractionEventRecorded;
use TitanZero\Interaction\Models\InteractionEvent;

class EventRecorder implements EventRecorderInterface
{
    public function record(string $eventType, array $data): void
    {
        $event = new InteractionEvent([
            'run_id' => $data['run_id'] ?? null,
            'event_type' => $eventType,
            'data' => $data,
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);
        $event->save();
        if (function_exists('event')) {
            event(new InteractionEventRecorded($eventType, $data));
        }
    }

    public function getEvents(int $runId): array
    {
        return InteractionEvent::where('run_id', $runId)->orderBy('occurred_at')->get()->toArray();
    }
}
