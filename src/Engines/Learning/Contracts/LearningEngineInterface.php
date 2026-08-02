<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Contracts;

interface LearningEngineInterface
{
    public function learn(array $data, string $type): void;
    public function getLearnedModels(): array;
    public function retrain(string $model): void;
}
