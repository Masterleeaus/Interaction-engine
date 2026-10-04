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
        'max_delay' => 300,
    ];
    private array $attempts = [];
    private $scheduler = null;

    public function retryWithBackoff(string $taskId): void
    {
        if (trim($taskId) === '') {
            throw new \InvalidArgumentException('Task identifier is required.');
        }
        if (!is_callable($this->scheduler)) {
            throw new \LogicException('Register a delayed-job scheduler before requesting a retry.');
        }

        $attempt = ($this->attempts[$taskId]['attempts'] ?? 0) + 1;
        if ($attempt > $this->retryPolicy['max_attempts']) {
            $this->attempts[$taskId] = ['attempts' => $attempt - 1, 'status' => 'exhausted'];
            return;
        }

        $base = (int) $this->retryPolicy['base_delay'];
        $multiplier = match ($this->retryPolicy['backoff']) {
            'fixed' => 1,
            'linear' => $attempt,
            'exponential' => 2 ** ($attempt - 1),
        };
        $delay = min((int) $this->retryPolicy['max_delay'], $base * $multiplier);
        $availableAt = gmdate(DATE_ATOM, time() + $delay);
        ($this->scheduler)($taskId, $attempt, $availableAt);
        $this->attempts[$taskId] = [
            'attempts' => $attempt,
            'status' => 'scheduled',
            'delay_seconds' => $delay,
            'available_at' => $availableAt,
        ];
    }

    public function setScheduler(callable $scheduler): void
    {
        $this->scheduler = $scheduler;
    }

    public function getTaskRetryState(string $taskId): array
    {
        return $this->attempts[$taskId] ?? ['attempts' => 0, 'status' => 'not_scheduled'];
    }

    public function getRetryPolicy(): array
    {
        return $this->retryPolicy;
    }

    public function setRetryPolicy(array $policy): void
    {
        $candidate = array_merge($this->retryPolicy, $policy);
        if (!in_array($candidate['backoff'], ['fixed', 'linear', 'exponential'], true)) {
            throw new \InvalidArgumentException('Backoff must be fixed, linear or exponential.');
        }
        if ((int) $candidate['max_attempts'] < 1 || (int) $candidate['base_delay'] < 0 || (int) $candidate['max_delay'] < 0) {
            throw new \InvalidArgumentException('Retry counts and delays must be non-negative, with at least one attempt.');
        }
        $this->retryPolicy = $candidate;
    }
}
