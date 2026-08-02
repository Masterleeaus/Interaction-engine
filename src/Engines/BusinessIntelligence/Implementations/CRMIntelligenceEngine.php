<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\CRMIntelligenceEngineInterface;
use Illuminate\Support\Facades\DB;

/**
 * Derives real (if simple) signals from the host app's own jobs/invoices
 * tables rather than returning fixed values per customer. Fixed during the
 * fix pass: the original returned an identical hardcoded 'high_value'
 * segment, $5000 lifetime value, and 0.2 churn risk for every customer ID.
 *
 * NOTE: assumes `jobs` and `invoices` tables exist on the host app with
 * customer_id/total/created_at columns — this is a WorkCore-style
 * assumption consistent with WorldModelEngine's use of `customers`/`jobs`
 * elsewhere in this library. Adjust column names to match your schema.
 */
class CRMIntelligenceEngine implements CRMIntelligenceEngineInterface
{
    public function getCustomerInsights(int $customerId): array
    {
        $jobCount = $this->safeCount('jobs', $customerId);
        $lifetimeValue = $this->safeSum('invoices', $customerId, 'total');
        $lastJob = $this->lastActivity('jobs', $customerId);

        return [
            'segment' => $this->getCustomerSegment($customerId),
            'lifetime_value' => $lifetimeValue,
            'job_count' => $jobCount,
            'last_active' => $lastJob,
        ];
    }

    public function predictChurn(int $customerId): float
    {
        $lastActive = $this->lastActivity('jobs', $customerId);
        if ($lastActive === null) {
            return 0.9; // no activity on record at all — high risk
        }
        $daysSince = now()->diffInDays($lastActive);
        // Simple recency-based risk curve: 0 risk at 0 days, ~1.0 by 365 days
        return round(min(1.0, $daysSince / 365), 2);
    }

    public function getCustomerSegment(int $customerId): string
    {
        $lifetimeValue = $this->safeSum('invoices', $customerId, 'total');

        return match (true) {
            $lifetimeValue >= 10000 => 'enterprise',
            $lifetimeValue >= 2000 => 'high_value',
            $lifetimeValue >= 200 => 'standard',
            default => 'new_or_low_activity',
        };
    }

    private function safeCount(string $table, int $customerId): int
    {
        try {
            return (int) DB::table($table)->where('customer_id', $customerId)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeSum(string $table, int $customerId, string $column): float
    {
        try {
            return (float) DB::table($table)->where('customer_id', $customerId)->sum($column);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    private function lastActivity(string $table, int $customerId): ?\DateTimeInterface
    {
        try {
            $row = DB::table($table)
                ->where('customer_id', $customerId)
                ->orderByDesc('created_at')
                ->first();
            return $row ? \Carbon\Carbon::parse($row->created_at) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
