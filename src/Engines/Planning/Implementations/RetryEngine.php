<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\RetryEngineInterface;
class RetryEngine implements RetryEngineInterface
{
    private array $retryPolicy = [
        'max_attempts' => 3,
        'backoff' => 'exponential',
        'base_delay' => 1,
    ];

    public function retryWithBackoff(string $taskId): void
    {
    }

    public function getRetryPolicy(): array
    {
        return $this->retryPolicy;
    }

    public function setRetryPolicy(array $policy): void
    {
        $this->retryPolicy = array_merge($this->retryPolicy, $policy);
    }
}
