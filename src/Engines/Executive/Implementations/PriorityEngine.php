<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\PriorityEngineInterface;
class PriorityEngine implements PriorityEngineInterface
{
    private array $overrides = [];

    public function rank(array $items, array $context = []): array
    {
        foreach ($items as &$item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Priority items must be arrays.');
            }
            $score = 0;
            if (array_key_exists('urgency', $item)) {
                $score += $this->weightedValue($item['urgency'], 10, 'urgency');
            }
            if (array_key_exists('impact', $item)) {
                $score += $this->weightedValue($item['impact'], 5, 'impact');
            }
            if (isset($context['user_role']) && $context['user_role'] === 'manager') {
                $score += 20;
            }
            $key = (string) ($item['id'] ?? $item['name'] ?? '');
            $score += $this->overrides[$key] ?? 0;
            $item['priority_score'] = $score;
        }
        unset($item);
        usort($items, fn($a, $b) => ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0));
        return $items;
    }

    public function getHighestPriority(array $items, array $context = []): ?array
    {
        $ranked = $this->rank($items, $context);
        return $ranked[0] ?? null;
    }

    public function setPriority(string $item, int $priority): void
    {
        if (trim($item) === '') {
            throw new \InvalidArgumentException('Priority override item name is required.');
        }
        $this->overrides[$item] = $priority;
    }

    private function weightedValue(mixed $value, float $weight, string $field): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            throw new \InvalidArgumentException("Priority {$field} must be a finite numeric value.");
        }
        return (float) $value * $weight;
    }
}
