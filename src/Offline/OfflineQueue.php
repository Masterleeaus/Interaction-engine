<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Offline;

use TitanZero\Interaction\Contracts\OfflineQueueInterface;
use TitanZero\Interaction\Models\QueuedCommand;

class OfflineQueue implements OfflineQueueInterface
{
    public function queue(string $capability, array $payload, array $metadata = []): void
    {
        QueuedCommand::create([
            'capability' => $capability,
            'payload' => $payload,
            'metadata' => $metadata,
            'status' => 'pending',
            'created_at' => now(),
            'attempts' => 0,
        ]);
    }

    public function getPending(): array
    {
        return QueuedCommand::where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function markSynced(int $id): void
    {
        QueuedCommand::where('id', $id)->update([
            'status' => 'synced',
            'synced_at' => now(),
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        QueuedCommand::where('id', $id)->update([
            'status' => 'failed',
            'error' => $error,
            'failed_at' => now(),
        ]);
    }

    public function countPending(): int
    {
        return QueuedCommand::where('status', 'pending')->count();
    }

    public function clear(): void
    {
        QueuedCommand::whereIn('status', ['synced', 'failed'])->delete();
    }
}