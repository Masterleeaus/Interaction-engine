<?php

declare(strict_types=1);

namespace TitanZero\Engines\Cognitive\Implementations;

use TitanZero\Engines\Cognitive\Contracts\ConceptEngineInterface;
class ConceptEngine implements ConceptEngineInterface
{
    private array $concepts = [];
    private array $relationships = [];

    public function createConcept(string $name, array $attributes): void
    {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Concept name is required.');
        }
        $this->concepts[$name] = $attributes;
    }

    public function relate(string $from, string $to, string $relationship): void
    {
        if (!isset($this->concepts[$from], $this->concepts[$to])) {
            throw new \InvalidArgumentException('Both concepts must exist before creating a relationship.');
        }
        if (trim($relationship) === '') {
            throw new \InvalidArgumentException('Relationship name is required.');
        }
        foreach ($this->relationships as $existing) {
            if ($existing === compact('from', 'to', 'relationship')) {
                return;
            }
        }
        $this->relationships[] = compact('from', 'to', 'relationship');
    }

    public function getRelationships(string $concept): array
    {
        return array_values(array_filter(
            $this->relationships,
            static fn(array $edge): bool => $edge['from'] === $concept || $edge['to'] === $concept,
        ));
    }

    public function getConcept(string $name): array
    {
        return $this->concepts[$name] ?? [];
    }

    public function listConcepts(): array
    {
        return array_keys($this->concepts);
    }
}
