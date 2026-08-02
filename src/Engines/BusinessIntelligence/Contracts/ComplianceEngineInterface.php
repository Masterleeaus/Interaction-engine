<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface ComplianceEngineInterface
{
    public function check(string $domain, array $data): array;
    public function getRegulations(string $domain): array;
    public function reportViolations(): array;
}
