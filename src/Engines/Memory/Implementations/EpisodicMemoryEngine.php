<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\EpisodicMemoryEngineInterface;
use Illuminate\Support\Facades\DB;

class EpisodicMemoryEngine implements EpisodicMemoryEngineInterface
{
    private array $lastConsolidation = ['groups' => 0, 'duplicates_removed' => 0];

    public function store(array $event): void
    {
        if ($event === []) {
            throw new \InvalidArgumentException('An episodic event cannot be empty.');
        }
        DB::table('episodic_memory')->insert([
            'event' => json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
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
        $this->lastConsolidation = DB::transaction(function (): array {
            $duplicates = DB::table('episodic_memory')
                ->select('event')
                ->selectRaw('COUNT(*) AS occurrence_count')
                ->groupBy('event')
                ->havingRaw('COUNT(*) > 1')
                ->get();
            $removed = 0;

            foreach ($duplicates as $duplicate) {
                $rows = DB::table('episodic_memory')
                    ->where('event', $duplicate->event)
                    ->orderBy('timestamp')
                    ->orderBy('id')
                    ->get(['id', 'timestamp']);
                $keeper = $rows->first();
                if ($keeper === null) {
                    continue;
                }

                $event = json_decode((string) $duplicate->event, true);
                $event = is_array($event) ? $event : ['value' => $duplicate->event];
                $event['_consolidation'] = [
                    'occurrences' => count($rows),
                    'first_seen' => (string) $keeper->timestamp,
                    'last_seen' => (string) $rows->last()->timestamp,
                ];
                DB::table('episodic_memory')->where('id', $keeper->id)->update([
                    'event' => json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ]);
                $ids = $rows->skip(1)->pluck('id')->all();
                if ($ids !== []) {
                    $removed += DB::table('episodic_memory')->whereIn('id', $ids)->delete();
                }
            }

            return ['groups' => $duplicates->count(), 'duplicates_removed' => $removed, 'consolidated_at' => gmdate(DATE_ATOM)];
        });
    }

    public function getLastConsolidation(): array
    {
        return $this->lastConsolidation;
    }

    public function forgetOlderThan(int $days): void
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Memory retention days cannot be negative.');
        }
        DB::table('episodic_memory')
            ->where('timestamp', '<', now()->subDays($days))
            ->delete();
    }
}
