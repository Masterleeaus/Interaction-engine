<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface MonitoringEngineInterface
{
    public function getStatus(): array;
    public function getMetrics(): array;
    public function setAlert(string $metric, float $threshold): void;
}
