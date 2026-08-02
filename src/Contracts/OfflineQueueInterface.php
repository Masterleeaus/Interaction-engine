<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

interface OfflineQueueInterface
{
    public function queue(string $capability, array $payload, array $metadata = []): void;
    public function getPending(): array;
    public function markSynced(int $id): void;
    public function markFailed(int $id, string $error): void;
    public function countPending(): int;
    public function clear(): void;
}