<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Contracts;

interface RiskEngineInterface
{
    public function assess(array $context): array;
    public function getRisks(): array;
    public function mitigate(string $risk, string $strategy): void;
    public function getRiskScore(array $context): float;
}
