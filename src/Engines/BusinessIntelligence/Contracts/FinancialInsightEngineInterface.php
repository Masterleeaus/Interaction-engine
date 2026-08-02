<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface FinancialInsightEngineInterface
{
    public function getRevenueForecast(): float;
    public function getCashFlow(): array;
    public function getProfitabilityMetrics(): array;
}
