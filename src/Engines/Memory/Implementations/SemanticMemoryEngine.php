<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\SemanticMemoryEngineInterface;
use Illuminate\Support\Facades\DB;

class SemanticMemoryEngine implements SemanticMemoryEngineInterface
{
    private array $lastConsolidation = ['groups' => 0, 'duplicates_removed' => 0];

    public function store(array $fact): void
    {
        if ($fact === []) {
            throw new \InvalidArgumentException('A semantic fact cannot be empty.');
        }
        DB::table('semantic_memory')->insert([
            'fact' => json_encode($fact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
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
        $this->lastConsolidation = DB::transaction(function (): array {
            $duplicates = DB::table('semantic_memory')
                ->select('fact')
                ->selectRaw('COUNT(*) AS occurrence_count')
                ->groupBy('fact')
                ->havingRaw('COUNT(*) > 1')
                ->get();
            $removed = 0;

            foreach ($duplicates as $duplicate) {
                $rows = DB::table('semantic_memory')
                    ->where('fact', $duplicate->fact)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get(['id', 'created_at']);
                $keeper = $rows->first();
                if ($keeper === null) {
                    continue;
                }
                $fact = json_decode((string) $duplicate->fact, true);
                $fact = is_array($fact) ? $fact : ['value' => $duplicate->fact];
                $fact['_consolidation'] = [
                    'occurrences' => count($rows),
                    'first_seen' => (string) $keeper->created_at,
                    'last_seen' => (string) $rows->last()->created_at,
                ];
                DB::table('semantic_memory')->where('id', $keeper->id)->update([
                    'fact' => json_encode($fact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ]);
                $ids = $rows->skip(1)->pluck('id')->all();
                if ($ids !== []) {
                    $removed += DB::table('semantic_memory')->whereIn('id', $ids)->delete();
                }
            }

            return ['groups' => $duplicates->count(), 'duplicates_removed' => $removed, 'consolidated_at' => gmdate(DATE_ATOM)];
        });
    }

    public function getLastConsolidation(): array
    {
        return $this->lastConsolidation;
    }

    public function getFacts(): array
    {
        return DB::table('semantic_memory')->get()->toArray();
    }

    public function forget(array $query): void
    {
        if (isset($query['id'])) {
            DB::table('semantic_memory')->where('id', $query['id'])->delete();
            return;
        }

        $keyword = trim((string) ($query['keyword'] ?? ''));
        if ($keyword === '') {
            throw new \InvalidArgumentException('Semantic memory deletion requires an id or non-empty keyword.');
        }
        $needle = strtolower($keyword);
        $ids = [];
        foreach (DB::table('semantic_memory')->get(['id', 'fact']) as $row) {
            if (str_contains(strtolower((string) $row->fact), $needle)) {
                $ids[] = $row->id;
            }
        }
        if ($ids !== []) {
            DB::table('semantic_memory')->whereIn('id', $ids)->delete();
        }
    }
}
