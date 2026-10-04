<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Compiler;

use TitanZero\Interaction\DTO\InteractionDefinition;
use TitanZero\Interaction\DTO\Question;
use TitanZero\Interaction\DTO\Section;

/** Pure definition pipeline shared by the Laravel adapter and standalone benchmarks. */
final class InteractionDefinitionCompiler
{
    public function __construct(
        private readonly SchemaValidator $schemaValidator,
        private readonly FragmentResolver $fragmentResolver,
        private readonly ConditionRegistry $conditionRegistry,
    ) {}

    public function compile(array $raw): InteractionDefinition
    {
        $this->schemaValidator->validate($raw);
        $resolved = $this->fragmentResolver->resolve($raw);
        $resolved = $this->conditionRegistry->expand($resolved);
        return $this->buildDTO($resolved);
    }

    private function buildDTO(array $resolved): InteractionDefinition
    {
        $sections = array_map(static fn(array $section): Section => new Section(
            id: (string) $section['id'],
            title: (string) $section['title'],
            questions: array_map(static fn(array $question): Question => new Question(
                key: (string) $question['key'],
                question: (string) $question['question'],
                responseType: (string) $question['response_type'],
                options: $question['options'] ?? [],
                optionsSource: $question['options_source'] ?? null,
                validation: $question['validation'] ?? [],
                default: $question['default'] ?? null,
                optional: $question['optional'] ?? false,
                handling: $question['handling'] ?? 'ask',
                priority: $question['priority'] ?? 'P1',
                condition: $question['condition'] ?? null,
                metadata: $question['metadata'] ?? [],
            ), $section['questions'] ?? []),
            metadata: $section['metadata'] ?? [],
        ), $resolved['sections']);

        return new InteractionDefinition(
            id: (string) $resolved['id'],
            version: (string) $resolved['version'],
            name: (string) $resolved['name'],
            description: (string) ($resolved['description'] ?? ''),
            category: (string) ($resolved['category'] ?? 'general'),
            permissions: $resolved['permissions'] ?? [],
            sections: $sections,
            capability: (string) $resolved['capability'],
            type: (string) ($resolved['type'] ?? 'wizard'),
            metadata: $resolved['metadata'] ?? [],
        );
    }
}
