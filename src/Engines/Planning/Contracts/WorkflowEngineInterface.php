<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface WorkflowEngineInterface
{
    public function registerWorkflow(string $name, array $steps): void;
    public function startWorkflow(string $name, array $input): string;
    public function getStatus(string $executionId): array;
    public function listWorkflows(): array;
}
