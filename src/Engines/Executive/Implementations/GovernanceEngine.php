<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\GovernanceEngineInterface;
use Illuminate\Support\Facades\DB;

class GovernanceEngine implements GovernanceEngineInterface
{
    public function log(array $event): void
    {
        if ($event === []) {
            throw new \InvalidArgumentException('Governance event cannot be empty.');
        }
        DB::table('governance_logs')->insert([
            'event' => json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
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
        $type = trim($type);
        if ($type === '') {
            throw new \InvalidArgumentException('Report type is required.');
        }
        $rows = DB::table('governance_logs')
            ->where('event', 'LIKE', '%' . addcslashes($type, '%_\\') . '%')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get(['event', 'created_at']);
        $events = [];
        foreach ($rows as $row) {
            $event = json_decode((string) $row->event, true);
            if (!is_array($event)) {
                continue;
            }
            if (($event['type'] ?? $event['event_type'] ?? null) === $type) {
                $events[] = $event + ['recorded_at' => (string) $row->created_at];
            }
        }
        return [
            'type' => $type,
            'count' => count($events),
            'data' => $events,
            'generated_at' => gmdate(DATE_ATOM),
        ];
    }
}
