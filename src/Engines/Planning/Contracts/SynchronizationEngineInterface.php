<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface SynchronizationEngineInterface
{
    public function registerSource(string $name, callable $reader): void;
    public function registerTarget(string $name, callable $writer): void;
    public function sync(string $source, string $target, array $trustedContext = []): void;
    public function getStatus(string $syncId): array;
    public function listSyncs(): array;
}
