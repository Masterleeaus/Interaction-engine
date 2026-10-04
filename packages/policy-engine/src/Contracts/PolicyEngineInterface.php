<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Contracts;

use TitanZero\Interaction\Authority\AuthorityDecision;
use TitanZero\Interaction\Authority\CapabilityPolicy;

interface PolicyEngineInterface
{
    public function decide(string $capability, array $payload): AuthorityDecision;
    public function registerCapabilityPolicy(CapabilityPolicy $policy): void;
    public function evaluate(string $capability, array $payload): bool;
    public function getReasons(): array;
    public function registerPolicy(string $capability, callable $policy): void;
}