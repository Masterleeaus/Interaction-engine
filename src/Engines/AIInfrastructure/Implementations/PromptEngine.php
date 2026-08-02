<?php

declare(strict_types=1);

namespace TitanZero\Engines\AIInfrastructure\Implementations;

use TitanZero\Engines\AIInfrastructure\Contracts\PromptEngineInterface;
class PromptEngine implements PromptEngineInterface
{
    public function build(array $components): string
    {
        return implode("\n", $components);
    }

    public function optimize(string $prompt): string
    {
        return $prompt;
    }

    public function getPromptTemplates(): array
    {
        return ['default', 'concise', 'detailed'];
    }
}
