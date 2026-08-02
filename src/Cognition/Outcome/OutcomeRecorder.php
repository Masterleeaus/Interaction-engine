<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Cognition\Outcome;

use TitanZero\Interaction\Cognition\Events\CognitiveEvent;
use TitanZero\Interaction\Cognition\Events\CognitiveEventStoreInterface;
use TitanZero\Interaction\Cognition\Events\CognitiveEventType;

final class OutcomeRecorder
{
    public function __construct(private readonly CognitiveEventStoreInterface $store) {}

    public function recordOutcome(string $tenantId, array $outcome, ?string $correlationId = null, ?string $subjectType = null, ?string $subjectId = null, array $scope = []): CognitiveEvent
    {
        $event = CognitiveEvent::create(
            type: CognitiveEventType::OutcomeObserved,
            tenantId: $tenantId,
            payload: $outcome,
            userId: $scope['user_id'] ?? null,
            deviceId: $scope['device_id'] ?? null,
            teamId: $scope['team_id'] ?? null,
            subjectType: $subjectType,
            subjectId: $subjectId,
            parentEventId: $scope['parent_event_id'] ?? null,
            correlationId: $correlationId,
            privacyClass: $scope['privacy_class'] ?? 'tenant_private',
            sequence: (int) ($scope['sequence'] ?? 0),
        );
        $this->store->append($event);
        return $event;
    }
}
