<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Contracts;

interface ProceduralMemoryEngineInterface
{
    public function store(array $skill): void;
    public function recall(array $query): array;
    public function getSkill(string $name): ?array;
    public function listSkills(): array;
    public function consolidate(): void;
}
