<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\SynchronizationEngineInterface;
class SynchronizationEngine implements SynchronizationEngineInterface
{
    private array $syncs = [];

    public function sync(string $source, string $target): void
    {
        $this->syncs[] = [
            'id' => uniqid('sync_'),
            'source' => $source,
            'target' => $target,
            'status' => 'completed',
            'timestamp' => now(),
        ];
    }

    public function getStatus(string $syncId): array
    {
        foreach ($this->syncs as $sync) {
            if ($sync['id'] === $syncId) {
                return $sync;
            }
        }
        return ['status' => 'not_found'];
    }

    public function listSyncs(): array
    {
        return $this->syncs;
    }
}
