<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Implementations;

use Illuminate\Support\Facades\DB;
use TitanZero\Engines\Learning\Contracts\BehaviourLearningEngineInterface;

class BehaviourLearningEngine implements BehaviourLearningEngineInterface
{
    private array $localActions = [];

    public function recordAction(int $userId, string $action, array $context): void
    {
        $record = ['user_id' => $userId, 'action' => $action, 'context' => $context, 'created_at' => gmdate(DATE_ATOM)];
        $this->localActions[$userId][] = $record;
        if (class_exists(DB::class)) {
            try {
                DB::table('user_actions')->insert([
                    'user_id' => $userId,
                    'action' => $action,
                    'context' => json_encode($context, JSON_THROW_ON_ERROR),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable) {
                // Local memory remains authoritative when persistence is unavailable.
            }
        }
    }

    public function getBehaviourProfile(int $userId): array
    {
        $actions = $this->localActions[$userId] ?? [];
        if (class_exists(DB::class)) {
            try {
                $rows = DB::table('user_actions')->where('user_id', $userId)->orderBy('created_at')->get()->toArray();
                if ($rows !== []) {
                    $actions = array_map(static fn($row): array => (array) $row, $rows);
                }
            } catch (\Throwable) {
            }
        }
        $frequency = [];
        foreach ($actions as $record) {
            $action = (string) ($record['action'] ?? 'unknown');
            $frequency[$action] = ($frequency[$action] ?? 0) + 1;
        }
        arsort($frequency);
        return ['actions' => $actions, 'frequency' => $frequency, 'total' => count($actions)];
    }

    public function predictNextAction(int $userId): ?string
    {
        $actions = $this->getBehaviourProfile($userId)['actions'];
        if (count($actions) < 2) {
            return null;
        }
        $last = (string) ($actions[array_key_last($actions)]['action'] ?? '');
        $transitions = [];
        for ($i = 0; $i < count($actions) - 1; $i++) {
            if (($actions[$i]['action'] ?? null) === $last) {
                $next = (string) ($actions[$i + 1]['action'] ?? '');
                if ($next !== '') {
                    $transitions[$next] = ($transitions[$next] ?? 0) + 1;
                }
            }
        }
        if ($transitions === []) {
            return null;
        }
        arsort($transitions);
        return (string) array_key_first($transitions);
    }
}
