<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Renderer;

use Illuminate\Support\Facades\View;
use TitanZero\Interaction\Contracts\RendererInterface;
use TitanZero\Interaction\DTO\InteractionDefinition;
use TitanZero\Interaction\DTO\Section;

class BladeRenderer implements RendererInterface
{
    public function __construct(private readonly string $view = 'interaction::run') {}

    public function render(InteractionDefinition $definition, array $state, array $options = []): string
    {
        return View::make($this->view, array_merge([
            'definition' => $definition,
            'state' => $state,
            'currentSection' => $this->getCurrentSection($definition, $state),
            'answers' => $state['answers'] ?? [],
        ], $options))->render();
    }

    private function getCurrentSection(InteractionDefinition $definition, array $state): ?Section
    {
        $index = (int) ($state['current_section_index'] ?? 0);
        return $definition->sections[$index] ?? null;
    }
}
