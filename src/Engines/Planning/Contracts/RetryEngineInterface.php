<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface RetryEngineInterface
{
    public function retryWithBackoff(string $taskId): void;
    public function getRetryPolicy(): array;
    public function setRetryPolicy(array $policy): void;
}
