<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\WorldModelEngineInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WorldModelEngine implements WorldModelEngineInterface
{
    private array $state = [];

    public function refresh(): void
    {
        $customers = DB::table('customers')->get();
        $jobs = DB::table('jobs')->get();
        $this->state = compact('customers', 'jobs');
        Cache::put('world_model', $this->state, 300);
    }

    public function getEntity(string $type, string $id): ?array
    {
        $state = Cache::get('world_model', $this->state);
        $collection = $state[$type] ?? collect();
        $entity = collect($collection)->firstWhere('id', $id);
        return $entity ? (array) $entity : null;
    }

    public function findEntities(string $type, array $criteria): array
    {
        $state = Cache::get('world_model', $this->state);
        $collection = collect($state[$type] ?? []);
        foreach ($criteria as $key => $value) {
            $collection = $collection->where($key, $value);
        }
        return $collection->map(fn($item) => (array) $item)->values()->all();
    }

    public function getState(): array
    {
        return Cache::get('world_model', $this->state);
    }
}
