<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Offline;

use TitanZero\Interaction\Contracts\OfflineQueueInterface;
use TitanZero\Interaction\Contracts\CommandBusInterface;
use TitanZero\Interaction\Contracts\EventRecorderInterface;
use TitanZero\Interaction\Contracts\ConflictResolverInterface;
use Illuminate\Support\Facades\Log;

class SyncEngine
{
    private OfflineQueueInterface $queue;
    private CommandBusInterface $commandBus;
    private EventRecorderInterface $eventRecorder;
    private ConflictResolverInterface $conflictResolver;

    public function __construct(
        OfflineQueueInterface $queue,
        CommandBusInterface $commandBus,
        EventRecorderInterface $eventRecorder,
        ConflictResolverInterface $conflictResolver
    ) {
        $this->queue = $queue;
        $this->commandBus = $commandBus;
        $this->eventRecorder = $eventRecorder;
        $this->conflictResolver = $conflictResolver;
    }

    public function sync(): array
    {
        $results = [
            'synced' => 0,
            'failed' => 0,
            'conflicts' => 0,
        ];

        $pending = $this->queue->getPending();
        if (empty($pending)) {
            return $results;
        }

        Log::info('Starting sync', ['count' => count($pending)]);

        foreach ($pending as $command) {
            try {
                // Check for conflicts
                $conflict = $this->conflictResolver->resolve($command);
                if ($conflict['conflict']) {
                    Log::warning('Conflict detected', ['command_id' => $command['id'], 'conflict' => $conflict['message']]);
                    $this->queue->markFailed($command['id'], 'Conflict: ' . $conflict['message']);
                    $results['conflicts']++;
                    continue;
                }

                // Execute the command
                $this->commandBus->dispatch($command['capability'], $command['payload']);
                $this->queue->markSynced($command['id']);
                $results['synced']++;

                $this->eventRecorder->record('command_synced', [
                    'command_id' => $command['id'],
                    'capability' => $command['capability'],
                ]);

            } catch (\Throwable $e) {
                Log::error('Sync failed for command', [
                    'command_id' => $command['id'],
                    'error' => $e->getMessage(),
                ]);
                $this->queue->markFailed($command['id'], $e->getMessage());
                $results['failed']++;
            }
        }

        Log::info('Sync completed', $results);
        return $results;
    }

    public function getStatus(): array
    {
        return [
            'pending' => $this->queue->countPending(),
            'total' => $this->queue->countPending() + $this->getSyncedCount() + $this->getFailedCount(),
        ];
    }

    private function getSyncedCount(): int
    {
        return \DB::table('queued_commands')->where('status', 'synced')->count();
    }

    private function getFailedCount(): int
    {
        return \DB::table('queued_commands')->where('status', 'failed')->count();
    }
}