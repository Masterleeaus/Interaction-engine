<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Healer;

use TitanZero\Interaction\Contracts\HealerInterface;
use TitanZero\Interaction\Models\InteractionRun;
use TitanZero\Interaction\Models\InteractionEvent;
use Illuminate\Support\Facades\Log;

class HealerEngine implements HealerInterface
{
    private array $healingStrategies = [];

    public function __construct()
    {
        $this->registerDefaultStrategies();
    }

    public function detectAnomalies(): array
    {
        $anomalies = [];

        // Detect stuck runs (in_progress for > 24 hours)
        $stuckRuns = InteractionRun::where('state', 'in_progress')
            ->where('updated_at', '<', now()->subHours(24))
            ->get();

        foreach ($stuckRuns as $run) {
            $anomalies[] = [
                'type' => 'stuck_run',
                'run_id' => $run->id,
                'user_id' => $run->user_id,
                'interaction_id' => $run->interaction_id,
                'message' => 'Run stuck for 24+ hours',
                'severity' => 'medium',
            ];
        }

        // Detect frequent failures
        $failedEvents = InteractionEvent::where('event_type', 'capability_executed')
            ->whereRaw("JSON_EXTRACT(data, '$.success') = false")
            ->where('occurred_at', '>', now()->subHours(24))
            ->count();

        if ($failedEvents > 10) {
            $anomalies[] = [
                'type' => 'high_failure_rate',
                'message' => "{$failedEvents} failures in the last 24 hours",
                'severity' => 'high',
            ];
        }

        return $anomalies;
    }

    public function heal(int $runId): void
    {
        $run = InteractionRun::find($runId);
        if (!$run) {
            return;
        }

        // Reset stuck runs
        if ($run->state === 'in_progress' && $run->updated_at < now()->subHours(24)) {
            $run->state = 'failed';
            $run->meta = array_merge($run->meta ?? [], [
                'healed' => true,
                'healed_at' => now(),
                'healing_reason' => 'Auto-healed: stuck in progress',
            ]);
            $run->save();
            Log::info('Healed stuck run', ['run_id' => $runId]);
        }
    }

    public function prevent(array $pattern): void
    {
        // Prevent future occurrences of a pattern
        $key = 'healing_prevent_' . md5(serialize($pattern));
        $preventions = \Cache::get($key, []);
        $preventions[] = [
            'pattern' => $pattern,
            'timestamp' => now(),
        ];
        \Cache::put($key, $preventions, 86400 * 30);
    }

    private function registerDefaultStrategies(): void
    {
        $this->registerStrategy('stuck_run', function($anomaly) {
            $this->heal($anomaly['run_id']);
            return true;
        });

        $this->registerStrategy('high_failure_rate', function($anomaly) {
            Log::warning('High failure rate detected', ['count' => $anomaly['message']]);
            // Could trigger notifications, slow down processing, etc.
            return true;
        });
    }

    public function registerStrategy(string $type, callable $strategy): void
    {
        $this->healingStrategies[$type] = $strategy;
    }
}