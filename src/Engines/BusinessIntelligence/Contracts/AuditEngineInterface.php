<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface AuditEngineInterface
{
    public function log(array $entry): void;
    public function query(array $criteria): array;
    public function getTrail(string $entity): array;
}
