<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface SynchronizationEngineInterface
{
    public function sync(string $source, string $target): void;
    public function getStatus(string $syncId): array;
    public function listSyncs(): array;
}
