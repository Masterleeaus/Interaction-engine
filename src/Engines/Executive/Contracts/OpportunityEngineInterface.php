<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Contracts;

interface OpportunityEngineInterface
{
    public function identify(array $context): array;
    public function prioritize(array $opportunities): array;
    public function capture(string $opportunity, array $plan): void;
    public function getTrackedOpportunities(): array;
}
