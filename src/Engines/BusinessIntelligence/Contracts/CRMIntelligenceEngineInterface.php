<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface CRMIntelligenceEngineInterface
{
    public function getCustomerInsights(int $customerId): array;
    public function predictChurn(int $customerId): float;
    public function getCustomerSegment(int $customerId): string;
}
