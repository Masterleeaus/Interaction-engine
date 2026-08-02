<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\AuditEngineInterface;
use Illuminate\Support\Facades\DB;

class AuditEngine implements AuditEngineInterface
{
    public function log(array $entry): void
    {
        DB::table('audit_logs')->insert([
            'entry' => json_encode($entry),
            'created_at' => now(),
        ]);
    }

    public function query(array $criteria): array
    {
        return DB::table('audit_logs')
            ->where('entry', 'LIKE', '%' . ($criteria['keyword'] ?? '') . '%')
            ->get()
            ->toArray();
    }

    public function getTrail(string $entity): array
    {
        return DB::table('audit_logs')
            ->where('entry', 'LIKE', '%' . $entity . '%')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }
}
