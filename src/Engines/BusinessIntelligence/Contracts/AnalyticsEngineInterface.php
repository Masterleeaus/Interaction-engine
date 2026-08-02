<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface AnalyticsEngineInterface
{
    public function analyze(string $dataset, array $metrics): array;
    public function trend(string $metric, int $period): array;
    public function getDashboard(): array;
}
