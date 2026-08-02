<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\SemanticMemoryEngineInterface;
use Illuminate\Support\Facades\DB;

class SemanticMemoryEngine implements SemanticMemoryEngineInterface
{
    public function store(array $fact): void
    {
        DB::table('semantic_memory')->insert([
            'fact' => json_encode($fact),
            'created_at' => now(),
        ]);
    }

    public function query(array $query): array
    {
        return DB::table('semantic_memory')
            ->where('fact', 'LIKE', '%' . ($query['keyword'] ?? '') . '%')
            ->get()
            ->toArray();
    }

    public function consolidate(): void
    {
    }

    public function getFacts(): array
    {
        return DB::table('semantic_memory')->get()->toArray();
    }
}
