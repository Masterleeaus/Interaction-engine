<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Wizard\Renderer;

use TitanZero\Interaction\Wizard\WizardSession;

final class ArrayRenderer implements WizardRendererInterface
{
    public function render(WizardSession $session): array
    {
        return [
            'session_id' => $session->id,
            'wizard_id' => $session->definition->id,
            'status' => $session->status,
            'step_index' => $session->stepIndex,
            'step' => $session->currentStep(),
            'data' => $session->data,
        ];
    }
}
