<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Implementations;

use TitanZero\Engines\AIInfrastructure\Contracts\PromptEngineInterface;
class PromptEngine implements PromptEngineInterface
{
    private array $templates = [
        'default' => "{{system}}\n\n{{user}}",
        'concise' => "{{system}}\nAnswer in a few direct sentences.\n\n{{user}}",
        'detailed' => "{{system}}\nGive a structured, evidence-aware answer. State uncertainty where relevant.\n\n{{user}}",
    ];

    public function build(array $components): string
    {
        if (isset($components['template'])) {
            $template = (string) $components['template'];
            if (!isset($this->templates[$template])) {
                throw new \InvalidArgumentException("Prompt template '{$template}' is not registered.");
            }
            $system = (string) ($components['system'] ?? '');
            $user = (string) ($components['user'] ?? '');
            return strtr($this->templates[$template], ['{{system}}' => $system, '{{user}}' => $user]);
        }

        $parts = [];
        foreach ($components as $role => $content) {
            if (is_array($content)) {
                $content = json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            }
            if (!is_scalar($content)) {
                throw new \InvalidArgumentException('Prompt components must be scalar text or JSON-compatible arrays.');
            }
            $text = trim((string) $content);
            if ($text !== '') {
                $parts[] = is_string($role) ? strtoupper($role) . ":\n" . $text : $text;
            }
        }
        return implode("\n\n", $parts);
    }

    public function optimize(string $prompt): string
    {
        $lines = preg_split('/\R/u', trim($prompt)) ?: [];
        $optimized = [];
        $blank = false;
        foreach ($lines as $line) {
            $isBlank = trim($line) === '';
            if ($isBlank && ($optimized === [] || $blank)) {
                continue;
            }
            $optimized[] = $isBlank ? '' : rtrim($line);
            $blank = $isBlank;
        }
        return implode("\n", $optimized);
    }

    public function getPromptTemplates(): array
    {
        return $this->templates;
    }

    public function registerTemplate(string $name, string $template): void
    {
        if (trim($name) === '' || !str_contains($template, '{{user}}')) {
            throw new \InvalidArgumentException('A prompt template needs a name and a {{user}} slot.');
        }
        $this->templates[$name] = $template;
    }
}
