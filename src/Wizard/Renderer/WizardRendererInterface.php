<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Renderer;

use TitanZero\Interaction\Wizard\WizardSession;

interface WizardRendererInterface
{
    public function render(WizardSession $session): array|string;
}
