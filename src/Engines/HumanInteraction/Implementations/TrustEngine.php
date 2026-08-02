<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Implementations;

use TitanZero\Engines\HumanInteraction\Contracts\TrustEngineInterface;
use Illuminate\Support\Facades\Cache;

class TrustEngine implements TrustEngineInterface
{
    public function getTrustScore(int $userId): float
    {
        return Cache::get('trust_' . $userId, 0.5);
    }

    public function updateTrust(int $userId, string $event): void
    {
        $score = $this->getTrustScore($userId);
        $change = match ($event) {
            'success' => 0.1,
            'failure' => -0.1,
            'correction' => 0.2,
            default => 0,
        };
        $score = min(max($score + $change, 0), 1);
        Cache::put('trust_' . $userId, $score, 86400 * 30);

        $counts = Cache::get('trust_events_' . $userId, ['success' => 0, 'failure' => 0, 'correction' => 0]);
        if (isset($counts[$event])) {
            $counts[$event]++;
            Cache::put('trust_events_' . $userId, $counts, 86400 * 30);
        }
    }

    public function getTrustFactors(int $userId): array
    {
        // Fixed during the fix pass: previously returned the identical
        // accuracy=0.8/reliability=0.7/transparency=0.9 for every user.
        // Derives real per-factor scores from this user's actual recorded
        // event history instead.
        $counts = Cache::get('trust_events_' . $userId, ['success' => 0, 'failure' => 0, 'correction' => 0]);
        $total = array_sum($counts) ?: 1;

        return [
            'accuracy' => round(1 - ($counts['failure'] / $total), 2),
            'reliability' => round($counts['success'] / $total, 2),
            'transparency' => round(($counts['success'] + $counts['correction']) / $total, 2),
        ];
    }
}
