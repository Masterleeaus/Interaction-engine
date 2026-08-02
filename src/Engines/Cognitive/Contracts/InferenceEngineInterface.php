<?php

declare(strict_types=1);

namespace TitanZero\Engines\Cognitive\Contracts;

interface InferenceEngineInterface
{
    public function infer(string $target, array $context): mixed;
    public function predict(string $target, array $context): mixed;
    public function estimate(string $target, array $context): float;
}
