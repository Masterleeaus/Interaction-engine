<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\DecisionEngineInterface;
class DecisionEngine implements DecisionEngineInterface
{
    public function evaluate(array $options, array $criteria): array
    {
        foreach ($criteria as $criterion => $weight) {
            if (!is_string($criterion) || !is_numeric($weight) || !is_finite((float) $weight)) {
                throw new \InvalidArgumentException('Decision criteria must map string names to finite numeric weights.');
            }
        }
        $results = [];
        foreach ($options as $index => $option) {
            if (!is_array($option)) {
                throw new \InvalidArgumentException('Decision options must be arrays.');
            }
            $score = 0.0;
            foreach ($criteria as $criterion => $weight) {
                if (isset($option[$criterion]) && is_numeric($option[$criterion])) {
                    $score += (float) $option[$criterion] * (float) $weight;
                }
            }
            $results[] = ['option' => $option, 'score' => $score, '_order' => $index];
        }
        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['_order'] <=> $b['_order']);
        foreach ($results as &$result) {
            unset($result['_order']);
        }
        unset($result);
        return $results;
    }

    public function decide(array $options, array $criteria): array
    {
        $evaluated = $this->evaluate($options, $criteria);
        return $evaluated[0] ?? ['option' => null, 'score' => 0];
    }

    public function getAlternatives(array $options): array
    {
        if ($options === []) {
            return [];
        }
        $ranked = array_values($options);
        usort($ranked, static fn($a, $b): int => ((float) (is_array($b) ? ($b['score'] ?? $b['confidence'] ?? 0) : 0)) <=> ((float) (is_array($a) ? ($a['score'] ?? $a['confidence'] ?? 0) : 0)));
        return array_slice($ranked, 1);
    }
}
