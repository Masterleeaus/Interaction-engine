<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface RecoveryEngineInterface
{
    public function recordFailure(string $failureId, string $taskId, string $message): void;
    public function recover(string $failureId): bool;
    public function getFailureLog(): array;
    public function retry(string $taskId): bool;
}
