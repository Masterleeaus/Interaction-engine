<?php

declare(strict_types=1);

namespace TitanZero\Engines\Memory\Implementations;

use TitanZero\Engines\Memory\Contracts\ProceduralMemoryEngineInterface;
use Illuminate\Support\Facades\Cache;

class ProceduralMemoryEngine implements ProceduralMemoryEngineInterface
{
    private array $skills = [];

    public function __construct()
    {
        $this->loadSkills();
    }

    public function store(array $skill): void
    {
        $name = trim((string) ($skill['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('A procedural skill must have a non-empty name.');
        }
        $skill['name'] = $name;
        $this->skills[$skill['name']] = $skill;
        $this->saveSkills();
    }

    public function recall(array $query): array
    {
        $keyword = strtolower(trim((string) ($query['keyword'] ?? '')));
        if ($keyword === '') {
            throw new \InvalidArgumentException('Procedural memory recall requires a non-empty keyword.');
        }
        $results = [];
        foreach ($this->skills as $skill) {
            $searchable = strtolower(implode(' ', array_filter([
                (string) ($skill['name'] ?? ''),
                (string) ($skill['description'] ?? ''),
                implode(' ', array_map('strval', (array) ($skill['steps'] ?? []))),
            ])));
            if (str_contains($searchable, $keyword)) {
                $skill['_match_score'] = substr_count($searchable, $keyword);
                $results[] = $skill;
            }
        }
        usort($results, static fn(array $a, array $b): int => $b['_match_score'] <=> $a['_match_score']);
        foreach ($results as &$skill) {
            unset($skill['_match_score']);
        }
        unset($skill);
        return $results;
    }

    public function getSkill(string $name): ?array
    {
        return $this->skills[$name] ?? null;
    }

    public function listSkills(): array
    {
        return array_keys($this->skills);
    }

    public function consolidate(): void
    {
        $this->saveSkills();
    }

    public function forget(array $query): void
    {
        $name = trim((string) ($query['name'] ?? ''));
        if ($name !== '') {
            unset($this->skills[$name]);
            $this->saveSkills();
            return;
        }
        $keyword = strtolower(trim((string) ($query['keyword'] ?? '')));
        if ($keyword === '') {
            throw new \InvalidArgumentException('Procedural memory deletion requires a name or non-empty keyword.');
        }
        foreach ($this->skills as $skillName => $skill) {
            $text = strtolower(json_encode($skill, JSON_UNESCAPED_SLASHES) ?: '');
            if (str_contains($text, $keyword)) {
                unset($this->skills[$skillName]);
            }
        }
        $this->saveSkills();
    }

    private function loadSkills(): void
    {
        $this->skills = Cache::get('procedural_memory', []);
    }

    private function saveSkills(): void
    {
        Cache::put('procedural_memory', $this->skills, 86400 * 30);
    }
}
