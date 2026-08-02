<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Contracts;

interface GeneralizationEngineInterface
{
    public function generalize(array $examples): array;
    public function applyGeneralization(array $general, array $newInstance): array;
    public function validateGeneralization(array $general, array $testSet): float;
}
