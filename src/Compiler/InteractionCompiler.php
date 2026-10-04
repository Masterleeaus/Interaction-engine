<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Compiler;

use TitanZero\Interaction\DTO\InteractionDefinition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class InteractionCompiler
{
    private string $definitionsPath;
    private InteractionDefinitionCompiler $definitionCompiler;

    public function __construct(
        SchemaValidator $schemaValidator,
        FragmentResolver $fragmentResolver,
        ConditionRegistry $conditionRegistry
    ) {
        $this->definitionsPath = config('interaction.definitions_path', base_path('interactions'));
        $this->definitionCompiler = new InteractionDefinitionCompiler($schemaValidator, $fragmentResolver, $conditionRegistry);
    }

    public function compile(string $id): InteractionDefinition
    {
        $cacheKey = 'interaction_definition_' . $id;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $raw = $this->loadRaw($id);
        if (!$raw) {
            throw new \RuntimeException("Definition '{$id}' not found.");
        }

        $definition = $this->definitionCompiler->compile($raw);
        Cache::put($cacheKey, $definition, config('interaction.cache_ttl', 3600));

        return $definition;
    }

    private function loadRaw(string $id): ?array
    {
        $paths = [
            $this->definitionsPath . '/' . $id . '.json',
            $this->definitionsPath . '/' . $id . '.yaml',
            $this->definitionsPath . '/' . str_replace('.', '/', $id) . '.json',
        ];
        foreach ($paths as $path) {
            if (File::exists($path)) {
                $content = File::get($path);
                if (str_ends_with($path, '.json')) {
                    return json_decode($content, true);
                } else {
                    return Yaml::parse($content);
                }
            }
        }
        return null;
    }
}
