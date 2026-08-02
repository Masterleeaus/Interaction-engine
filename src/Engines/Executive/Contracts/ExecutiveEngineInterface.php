<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Contracts;

interface ExecutiveEngineInterface
{
    public function decide(): array;
    public function interrupt(string $event): void;
    public function pause(string $workflowId): void;
    public function resume(string $workflowId): void;
    public function prioritize(array $tasks): array;
    public function getCurrentFocus(): ?string;
    public function getStatus(): array;
}
