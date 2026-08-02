<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\AutomationEngineInterface;
class AutomationEngine implements AutomationEngineInterface
{
    private array $automations = [];

    public function automate(string $task, array $schedule): void
    {
        $this->automations[] = ['task' => $task, 'schedule' => $schedule];
    }

    public function getAutomations(): array
    {
        return $this->automations;
    }

    public function trigger(string $automationId): void
    {
    }
}
