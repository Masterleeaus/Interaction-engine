<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Contracts;

interface AutomationEngineInterface
{
    public function registerTaskHandler(string $task, callable $handler): void;
    public function automate(string $task, array $schedule): void;
    public function getAutomations(): array;
    public function trigger(string $automationId): void;
}
