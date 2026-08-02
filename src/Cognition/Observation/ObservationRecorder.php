<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Cognition\Observation;

use TitanZero\Interaction\Cognition\Events\CognitiveEvent;
use TitanZero\Interaction\Cognition\Events\CognitiveEventStoreInterface;
use TitanZero\Interaction\Cognition\Events\CognitiveEventType;

final class ObservationRecorder
{
    public function __construct(private readonly CognitiveEventStoreInterface $store) {}

    public function record(string $tenantId, array $facts, array $scope = []): CognitiveEvent
    {
        $event = CognitiveEvent::create(
            type: CognitiveEventType::ObservationRecorded,
            tenantId: $tenantId,
            payload: ['observed_facts' => $facts],
            userId: $scope['user_id'] ?? null,
            deviceId: $scope['device_id'] ?? null,
            teamId: $scope['team_id'] ?? null,
            subjectType: $scope['subject_type'] ?? null,
            subjectId: $scope['subject_id'] ?? null,
            correlationId: $scope['correlation_id'] ?? null,
            privacyClass: $scope['privacy_class'] ?? 'tenant_private',
            sequence: (int) ($scope['sequence'] ?? 0),
        );
        $this->store->append($event);
        return $event;
    }
}
