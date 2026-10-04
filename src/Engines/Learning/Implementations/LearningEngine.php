<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Implementations;

use TitanZero\Engines\Learning\Contracts\LearningEngineInterface;
class LearningEngine implements LearningEngineInterface
{
    private array $models = [];

    public function learn(array $data, string $type): void
    {
        $type = trim($type);
        if ($type === '') {
            throw new \InvalidArgumentException('Learning model type is required.');
        }
        if ($data === []) {
            throw new \InvalidArgumentException('At least one training observation is required.');
        }
        $model = $this->models[$type] ?? [
            'type' => $type,
            'observations' => [],
            'version' => 0,
            'trained' => false,
        ];
        $model['observations'][] = $data;
        if (count($model['observations']) > 10000) {
            array_shift($model['observations']);
        }
        $model['trained'] = false;
        $this->models[$type] = $model;
    }

    public function getLearnedModels(): array
    {
        return $this->models;
    }

    public function retrain(string $model): void
    {
        if (!isset($this->models[$model])) {
            throw new \InvalidArgumentException("Learning model '{$model}' has no observations.");
        }
        $observations = $this->models[$model]['observations'];
        $numericSums = [];
        $numericCounts = [];
        $categoricalCounts = [];
        foreach ($observations as $observation) {
            foreach ($observation as $field => $value) {
                if (is_numeric($value)) {
                    $numericSums[$field] = ($numericSums[$field] ?? 0.0) + (float) $value;
                    $numericCounts[$field] = ($numericCounts[$field] ?? 0) + 1;
                } elseif (is_scalar($value)) {
                    $category = (string) $value;
                    $categoricalCounts[$field][$category] = ($categoricalCounts[$field][$category] ?? 0) + 1;
                }
            }
        }
        $numericMeans = [];
        foreach ($numericSums as $field => $sum) {
            $numericMeans[$field] = $sum / $numericCounts[$field];
        }
        $this->models[$model]['learned_summary'] = [
            'sample_count' => count($observations),
            'numeric_means' => $numericMeans,
            'categorical_counts' => $categoricalCounts,
        ];
        $this->models[$model]['version']++;
        $this->models[$model]['trained'] = true;
        $this->models[$model]['trained_at'] = gmdate(DATE_ATOM);
    }
}
