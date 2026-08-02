<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Repositories;

use TitanZero\Interaction\Models\InteractionRun;

class EloquentInteractionRunRepository implements InteractionRunRepositoryInterface
{
    public function create(int $userId, string $interactionId, string $definitionVersion): int
    {
        $run = InteractionRun::create([
            'user_id' => $userId,
            'interaction_id' => $interactionId,
            'definition_version' => $definitionVersion,
            'current_section_index' => 0,
            'answers' => [],
            'state' => 'started',
            'meta' => [],
        ]);
        return $run->id;
    }

    public function find(int $runId): ?array
    {
        $run = InteractionRun::find($runId);
        return $run ? $run->toArray() : null;
    }

    public function update(int $runId, array $data): void
    {
        InteractionRun::where('id', $runId)->update($data);
    }

    public function findActiveForUser(int $userId, string $interactionId): ?array
    {
        $run = InteractionRun::where('user_id', $userId)
            ->where('interaction_id', $interactionId)
            ->whereIn('state', ['started', 'in_progress'])
            ->latest()
            ->first();
        return $run ? $run->toArray() : null;
    }
}