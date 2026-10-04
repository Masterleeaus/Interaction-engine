<?php

declare(strict_types=1);

namespace TitanZero\Engines\Planning\Implementations;

use TitanZero\Engines\Planning\Contracts\ResourceAllocationEngineInterface;
class ResourceAllocationEngine implements ResourceAllocationEngineInterface
{
    private array $allocations = [];

    public function allocate(string $resource, string $task): void
    {
        if (trim($resource) === '' || trim($task) === '') {
            throw new \InvalidArgumentException('Resource and task identifiers are required.');
        }
        $this->allocations[$resource] = $task;
    }

    public function deallocate(string $resource, string $task): void
    {
        if (($this->allocations[$resource] ?? null) === $task) {
            unset($this->allocations[$resource]);
        }
    }

    public function getResourceAllocation(): array
    {
        return $this->allocations;
    }
}
