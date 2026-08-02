<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Commands;

use Illuminate\Console\Command;
use TitanZero\Interaction\Offline\SyncEngine;

class SyncInteractionCommand extends Command
{
    protected $signature = 'interaction:sync {--force : Force sync even if online}';
    protected $description = 'Synchronize queued offline commands';

    public function handle(SyncEngine $syncEngine)
    {
        $this->info('Starting sync...');
        $results = $syncEngine->sync();

        $this->info("Synced: {$results['synced']}");
        $this->info("Failed: {$results['failed']}");
        $this->info("Conflicts: {$results['conflicts']}");

        if ($results['failed'] > 0 || $results['conflicts'] > 0) {
            return 1;
        }
        return 0;
    }
}