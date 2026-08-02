<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Registry;

use TitanZero\Interaction\Compiler\InteractionCompiler;
use TitanZero\Interaction\DTO\InteractionDefinition;

class InteractionRegistry
{
    private InteractionCompiler $compiler;
    private array $cache = [];

    public function __construct(InteractionCompiler $compiler)
    {
        $this->compiler = $compiler;
    }

    public function get(string $id): ?InteractionDefinition
    {
        if (!isset($this->cache[$id])) {
            $this->cache[$id] = $this->compiler->compile($id);
        }
        return $this->cache[$id];
    }

    public function all(): array
    {
        $ids = $this->discover();
        foreach ($ids as $id) {
            if (!isset($this->cache[$id])) {
                $this->cache[$id] = $this->compiler->compile($id);
            }
        }
        return $this->cache;
    }

    private function discover(): array
    {
        $path = config('interaction.definitions_path', base_path('interactions'));
        $files = glob($path . '/*.json') ?: [];
        return array_map(fn($f) => basename($f, '.json'), $files);
    }
}