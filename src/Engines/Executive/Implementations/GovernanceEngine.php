<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\GovernanceEngineInterface;
use Illuminate\Support\Facades\DB;

class GovernanceEngine implements GovernanceEngineInterface
{
    public function log(array $event): void
    {
        DB::table('governance_logs')->insert([
            'event' => json_encode($event),
            'created_at' => now(),
        ]);
    }

    public function getAuditTrail(string $entity): array
    {
        return DB::table('governance_logs')
            ->where('event', 'LIKE', '%' . $entity . '%')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    public function checkCompliance(string $domain): array
    {
        // Looks for governance events already logged against this domain
        // that were tagged as issues/violations — a real (if limited)
        // check based on this engine's own audit trail, rather than an
        // unconditional pass. A domain with no logged history is reported
        // as compliant because there's nothing on record to flag, not
        // because compliance was verified.
        try {
            $recentIssues = DB::table('governance_logs')
                ->where('event', 'LIKE', '%' . $domain . '%')
                ->where('event', 'LIKE', '%"severity":"violation"%')
                ->where('created_at', '>=', now()->subDays(90))
                ->get();
        } catch (\Throwable) {
            return ['compliant' => null, 'issues' => [], 'note' => 'governance_logs query failed'];
        }

        $issues = $recentIssues->map(fn ($row) => json_decode($row->event, true))->filter()->values()->all();

        return ['compliant' => empty($issues), 'issues' => $issues];
    }

    public function report(string $type): array
    {
        return ['data' => [], 'type' => $type];
    }
}
