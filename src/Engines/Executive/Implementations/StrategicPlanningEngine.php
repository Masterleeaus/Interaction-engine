<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\StrategicPlanningEngineInterface;
use Illuminate\Support\Facades\Cache;

class StrategicPlanningEngine implements StrategicPlanningEngineInterface
{
    private array $strategies = [];

    public function __construct()
    {
        $this->loadStrategies();
    }

    public function defineStrategy(string $name, array $objectives): void
    {
        if (trim($name) === '' || $objectives === []) {
            throw new \InvalidArgumentException('A strategy name and at least one objective are required.');
        }
        foreach ($objectives as $objective) {
            if (!is_string($objective) && !is_array($objective)) {
                throw new \InvalidArgumentException('Strategy objectives must be names or objective records.');
            }
        }
        $this->strategies[$name] = [
            'name' => $name,
            'objectives' => $objectives,
            'created_at' => gmdate(DATE_ATOM),
            'status' => 'active',
        ];
        $this->saveStrategies();
    }

    public function getActiveStrategies(): array
    {
        return array_values(array_filter($this->strategies, fn($s) => $s['status'] === 'active'));
    }

    public function evaluateStrategy(string $name): array
    {
        if (!isset($this->strategies[$name])) {
            return ['error' => 'Strategy not found'];
        }

        $objectives = (array) $this->strategies[$name]['objectives'];
        $progressValues = array_map(static function (mixed $objective): float {
            if (!is_array($objective)) {
                return 0.0;
            }
            if (($objective['completed'] ?? false) === true) {
                return 100.0;
            }
            return max(0.0, min(100.0, (float) ($objective['progress'] ?? 0)));
        }, $objectives);
        $progress = $progressValues === [] ? 0.0 : array_sum($progressValues) / count($progressValues);

        return [
            'strategy' => $name,
            'progress' => round($progress, 2),
            'completed_objectives' => count(array_filter($progressValues, static fn(float $value): bool => $value >= 100)),
            'objective_count' => count($objectives),
            'status' => $this->strategies[$name]['status'],
            'evaluated_at' => gmdate(DATE_ATOM),
        ];
    }

    public function updateStrategy(string $name, array $updates): void
    {
        if (isset($this->strategies[$name])) {
            $allowed = ['objectives', 'status', 'owner', 'due_at', 'description'];
            $updates = array_intersect_key($updates, array_flip($allowed));
            if (isset($updates['status']) && !in_array($updates['status'], ['active', 'paused', 'completed', 'archived'], true)) {
                throw new \InvalidArgumentException('Strategy status must be active, paused, completed or archived.');
            }
            if (isset($updates['objectives']) && !is_array($updates['objectives'])) {
                throw new \InvalidArgumentException('Strategy objectives must be an array.');
            }
            $this->strategies[$name] = array_merge($this->strategies[$name], $updates);
            $this->strategies[$name]['updated_at'] = gmdate(DATE_ATOM);
            $this->saveStrategies();
        }
    }

    private function loadStrategies(): void
    {
        $this->strategies = Cache::get('strategic_plans', []);
    }

    private function saveStrategies(): void
    {
        Cache::put('strategic_plans', $this->strategies, 86400 * 30);
    }
}
