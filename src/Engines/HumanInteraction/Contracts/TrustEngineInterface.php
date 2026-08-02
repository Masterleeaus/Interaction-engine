<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Contracts;

interface TrustEngineInterface
{
    public function getTrustScore(int $userId): float;
    public function updateTrust(int $userId, string $event): void;
    public function getTrustFactors(int $userId): array;
}
