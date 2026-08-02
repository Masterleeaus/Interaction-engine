<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Implementations;

use TitanZero\Engines\Learning\Contracts\LearningEngineInterface;
class LearningEngine implements LearningEngineInterface
{
    private array $models = [];

    public function learn(array $data, string $type): void
    {
        $this->models[$type] = $data;
    }

    public function getLearnedModels(): array
    {
        return $this->models;
    }

    public function retrain(string $model): void
    {
    }
}
