<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Contracts;

interface ResourceAllocationEngineInterface
{
    public function allocate(string $resource, string $task): void;
    public function deallocate(string $resource, string $task): void;
    public function getResourceAllocation(): array;
}
