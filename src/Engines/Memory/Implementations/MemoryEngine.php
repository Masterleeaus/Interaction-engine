<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\MemoryEngineInterface;
use TitanZero\Engines\Memory\Contracts\EpisodicMemoryEngineInterface;
use TitanZero\Engines\Memory\Contracts\SemanticMemoryEngineInterface;
use TitanZero\Engines\Memory\Contracts\ProceduralMemoryEngineInterface;

class MemoryEngine implements MemoryEngineInterface
{
    public function __construct(
        private EpisodicMemoryEngineInterface $episodic,
        private SemanticMemoryEngineInterface $semantic,
        private ProceduralMemoryEngineInterface $procedural
    ) {}

    public function store(string $type, array $data): void
    {
        match ($type) {
            'episodic' => $this->episodic->store($data),
            'semantic' => $this->semantic->store($data),
            'procedural' => $this->procedural->store($data),
            default => null,
        };
    }

    public function recall(string $type, array $query): array
    {
        return match ($type) {
            'episodic' => $this->episodic->recall($query),
            'semantic' => $this->semantic->query($query),
            'procedural' => $this->procedural->recall($query),
            default => [],
        };
    }

    public function forget(string $type, array $query): void
    {
        if ($type === 'episodic' && isset($query['older_than_days'])) {
            $this->episodic->forgetOlderThan((int) $query['older_than_days']);
        }
    }

    public function consolidate(): void
    {
        $this->episodic->consolidate();
        $this->semantic->consolidate();
        $this->procedural->consolidate();
    }
}
