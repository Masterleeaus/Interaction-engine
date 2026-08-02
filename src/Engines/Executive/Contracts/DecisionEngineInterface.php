<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Contracts;

interface DecisionEngineInterface
{
    public function evaluate(array $options, array $criteria): array;
    public function decide(array $options, array $criteria): array;
    public function getAlternatives(array $options): array;
}
