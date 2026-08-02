<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Implementations;

use TitanZero\Engines\Learning\Contracts\ReinforcementLearningEngineInterface;
use Illuminate\Support\Facades\Cache;

class ReinforcementLearningEngine implements ReinforcementLearningEngineInterface
{
    private array $qTable = [];

    public function __construct()
    {
        $this->qTable = Cache::get('rl_qtable', []);
    }

    public function getAction(array $state): string
    {
        $actions = ['action1', 'action2', 'action3'];
        $bestAction = $actions[0];
        $bestValue = -INF;
        foreach ($actions as $action) {
            $value = $this->qTable[md5(serialize($state) . $action)] ?? 0;
            if ($value > $bestValue) {
                $bestValue = $value;
                $bestAction = $action;
            }
        }
        return $bestAction;
    }

    public function updateQValue(array $state, string $action, float $reward): void
    {
        $key = md5(serialize($state) . $action);
        $this->qTable[$key] = ($this->qTable[$key] ?? 0) + 0.1 * ($reward - ($this->qTable[$key] ?? 0));
        Cache::put('rl_qtable', $this->qTable, 86400 * 30);
    }

    public function getPolicy(): array
    {
        return ['policy' => 'epsilon_greedy', 'epsilon' => 0.1];
    }
}
