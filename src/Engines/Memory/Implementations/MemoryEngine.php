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
        switch ($type) {
            case 'episodic': $this->episodic->store($data); return;
            case 'semantic': $this->semantic->store($data); return;
            case 'procedural': $this->procedural->store($data); return;
            default: throw new \InvalidArgumentException("Unknown memory type '{$type}'.");
        }
    }

    public function recall(string $type, array $query): array
    {
        return match ($type) {
            'episodic' => $this->episodic->recall($query),
            'semantic' => $this->semantic->query($query),
            'procedural' => $this->procedural->recall($query),
            default => throw new \InvalidArgumentException("Unknown memory type '{$type}'."),
        };
    }

    public function forget(string $type, array $query): void
    {
        switch ($type) {
            case 'episodic':
                if (!isset($query['older_than_days'])) {
                    throw new \InvalidArgumentException('Episodic memory deletion requires older_than_days.');
                }
                $this->episodic->forgetOlderThan((int) $query['older_than_days']);
                return;
            case 'semantic': $this->semantic->forget($query); return;
            case 'procedural': $this->procedural->forget($query); return;
            default: throw new \InvalidArgumentException("Unknown memory type '{$type}'.");
        }
    }

    public function consolidate(): void
    {
        $this->episodic->consolidate();
        $this->semantic->consolidate();
        $this->procedural->consolidate();
    }
}
