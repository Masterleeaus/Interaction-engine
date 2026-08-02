<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\EpisodicMemoryEngineInterface;
use Illuminate\Support\Facades\DB;

class EpisodicMemoryEngine implements EpisodicMemoryEngineInterface
{
    public function store(array $event): void
    {
        DB::table('episodic_memory')->insert([
            'event' => json_encode($event),
            'timestamp' => now(),
        ]);
    }

    public function recall(array $query): array
    {
        return DB::table('episodic_memory')
            ->where('timestamp', '>', $query['since'] ?? '1900-01-01')
            ->get()
            ->toArray();
    }

    public function consolidate(): void
    {
    }

    public function forgetOlderThan(int $days): void
    {
        DB::table('episodic_memory')
            ->where('timestamp', '<', now()->subDays($days))
            ->delete();
    }
}
