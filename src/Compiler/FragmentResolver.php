<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Compiler;

class FragmentResolver
{
    public function __construct(private readonly ?string $fragmentsPath = null) {}

    public function resolve(array $definition): array
    {
        if (isset($definition['include'])) {
            $fragment = $this->loadFragment($definition['include']);
            $definition = $this->mergeFragments($fragment, $definition);
        }
        if (isset($definition['extends'])) {
            $parent = $this->loadFragment($definition['extends']);
            $definition = $this->mergeInheritance($parent, $definition);
        }
        return $definition;
    }

    private function loadFragment(string $path): array
    {
        if (trim($path) === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            throw new \InvalidArgumentException('Fragment references must be relative names inside the fragment directory.');
        }
        $base = $this->fragmentsPath ?? dirname(__DIR__, 2) . '/interactions/fragments';
        $fullPath = rtrim($base, '/') . '/' . $path . '.json';
        if (!is_file($fullPath)) {
            throw new \RuntimeException("Fragment '{$path}' not found.");
        }
        $contents = file_get_contents($fullPath);
        if ($contents === false) {
            throw new \RuntimeException("Fragment '{$path}' could not be read.");
        }
        $fragment = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($fragment)) {
            throw new \UnexpectedValueException("Fragment '{$path}' must contain a JSON object.");
        }
        return $fragment;
    }

    private function mergeFragments(array $fragment, array $definition): array
    {
        if (isset($fragment['sections'])) {
            $definition['sections'] = array_merge($fragment['sections'], $definition['sections'] ?? []);
        }
        return array_merge($fragment, $definition);
    }

    private function mergeInheritance(array $parent, array $child): array
    {
        return array_merge($parent, $child);
    }
}
