<?php

declare(strict_types=1);

namespace TitanZero\Interaction\AI;

final readonly class ActionProposal
{
    public function __construct(
        public string $capability,
        public array $payload,
        public string $rationale,
        public string $model,
    ) {}
}
