<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\AnalyticsEngineInterface;
use Illuminate\Support\Facades\DB;

/**
 * Real statistics over $metrics, and a trend computed from actual
 * interaction_events rows rather than a fixed 'increasing'/5%. Fixed
 * during the fix pass: analyze() always returned an empty results array
 * and trend() always claimed a 5% increase regardless of $metric/$period.
 */
class AnalyticsEngine implements AnalyticsEngineInterface
{
    public function analyze(string $dataset, array $metrics): array
    {
        $results = [];
        foreach ($metrics as $name => $values) {
            $numericName = is_int($name) ? 'series_' . $name : (string) $name;
            $data = is_array($values) ? $values : [$values];
            $numeric = array_filter($data, 'is_numeric');

            $results[$numericName] = empty($numeric) ? null : [
                'count' => count($numeric),
                'sum' => array_sum($numeric),
                'avg' => round(array_sum($numeric) / count($numeric), 2),
                'min' => min($numeric),
                'max' => max($numeric),
            ];
        }

        return ['dataset' => $dataset, 'results' => $results];
    }

    public function trend(string $metric, int $period): array
    {
        if (trim($metric) === '' || $period < 2) {
            throw new \InvalidArgumentException('A metric name and a period of at least two days are required.');
        }
        try {
            $since = now()->subDays($period);
            $mid = now()->subDays((int) ($period / 2));

            $firstHalf = (int) DB::table('interaction_events')
                ->where('event_type', $metric)
                ->whereBetween('occurred_at', [$since, $mid])
                ->count();
            $secondHalf = (int) DB::table('interaction_events')
                ->where('event_type', $metric)
                ->whereBetween('occurred_at', [$mid, now()])
                ->count();
        } catch (\Throwable) {
            return ['trend' => 'unknown', 'percent' => 0, 'reason' => 'query failed'];
        }

        if ($firstHalf === 0) {
            return ['trend' => $secondHalf > 0 ? 'increasing' : 'flat', 'percent' => $secondHalf > 0 ? 100 : 0];
        }

        $percent = round((($secondHalf - $firstHalf) / $firstHalf) * 100, 1);

        return [
            'trend' => $percent > 0 ? 'increasing' : ($percent < 0 ? 'decreasing' : 'flat'),
            'percent' => abs($percent),
        ];
    }

    public function getDashboard(): array
    {
        try {
            $rows = DB::table('interaction_events')
                ->select('event_type')
                ->selectRaw('COUNT(*) AS event_count')
                ->where('occurred_at', '>=', now()->subDays(30))
                ->groupBy('event_type')
                ->orderByDesc('event_count')
                ->get();
        } catch (\Throwable $error) {
            return [
                'available' => false,
                'window_days' => 30,
                'key_metrics' => [],
                'reason' => 'Host interaction_events schema unavailable: ' . $error->getMessage(),
                'generated_at' => gmdate(DATE_ATOM),
            ];
        }
        $metrics = [];
        foreach ($rows as $row) {
            $metrics[(string) $row->event_type] = (int) $row->event_count;
        }
        return [
            'available' => true,
            'window_days' => 30,
            'event_total' => array_sum($metrics),
            'key_metrics' => $metrics,
            'generated_at' => gmdate(DATE_ATOM),
        ];
    }
}
