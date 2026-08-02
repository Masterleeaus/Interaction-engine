<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Renderer;

use TitanZero\Interaction\Wizard\WizardSession;

final class HybridRenderer implements WizardRendererInterface
{
    public function __construct(
        private readonly ArrayRenderer $arrayRenderer = new ArrayRenderer(),
        private readonly ConversationalRenderer $conversationalRenderer = new ConversationalRenderer(),
    ) {}

    public function render(WizardSession $session): array
    {
        return [
            'message' => $this->conversationalRenderer->render($session),
            'view_model' => $this->arrayRenderer->render($session),
        ];
    }
}
