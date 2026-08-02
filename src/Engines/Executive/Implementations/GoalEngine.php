<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\GoalEngineInterface;
use Illuminate\Support\Facades\Cache;

class GoalEngine implements GoalEngineInterface
{
    private array $goals = [];

    public function __construct()
    {
        $this->loadGoals();
    }

    public function setGoal(string $name, string $description, array $targets = []): void
    {
        $this->goals[$name] = [
            'name' => $name,
            'description' => $description,
            'targets' => $targets,
            'progress' => 0,
            'status' => 'active',
        ];
        $this->saveGoals();
    }

    public function updateProgress(string $name, float $progress): void
    {
        if (isset($this->goals[$name])) {
            $this->goals[$name]['progress'] = min($progress, 100);
            if ($this->goals[$name]['progress'] >= 100) {
                $this->goals[$name]['status'] = 'completed';
            }
            $this->saveGoals();
        }
    }

    public function getGoals(): array
    {
        return $this->goals;
    }

    public function getNextMilestones(): array
    {
        $milestones = [];
        foreach ($this->goals as $goal) {
            if ($goal['status'] === 'active') {
                $next = 100 - $goal['progress'];
                if ($next > 0) {
                    $milestones[] = [
                        'goal' => $goal['name'],
                        'remaining' => $next,
                        'target' => $goal['targets'] ?? [],
                    ];
                }
            }
        }
        return $milestones;
    }

    public function getOverallProgress(): float
    {
        $total = count($this->goals);
        if ($total === 0) {
            return 0;
        }
        $sum = array_sum(array_column($this->goals, 'progress'));
        return $sum / $total;
    }

    private function loadGoals(): void
    {
        $this->goals = Cache::get('goals', []);
    }

    private function saveGoals(): void
    {
        Cache::put('goals', $this->goals, 86400 * 30);
    }
}
