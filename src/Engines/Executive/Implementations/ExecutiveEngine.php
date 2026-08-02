<?php

declare(strict_types=1);

namespace TitanZero\Engines\Executive\Implementations;

use TitanZero\Engines\Executive\Contracts\ExecutiveEngineInterface;
use TitanZero\Engines\Planning\Contracts\PlanningEngineInterface;
use TitanZero\Engines\Cognitive\Contracts\ReasoningEngineInterface;
use TitanZero\Engines\Learning\Contracts\PredictionEngineInterface;

class ExecutiveEngine implements ExecutiveEngineInterface
{
    private PlanningEngineInterface $planner;
    private ReasoningEngineInterface $reasoner;
    private PredictionEngineInterface $predictor;
    private array $focusStack = [];
    private array $interruptions = [];
    private array $pausedWorkflows = [];
    private string $currentFocus = 'idle';

    public function __construct(
        PlanningEngineInterface $planner,
        ReasoningEngineInterface $reasoner,
        PredictionEngineInterface $predictor
    ) {
        $this->planner = $planner;
        $this->reasoner = $reasoner;
        $this->predictor = $predictor;
    }

    public function decide(): array
    {
        if (!empty($this->interruptions)) {
            $event = array_shift($this->interruptions);
            $this->currentFocus = $event;
            return ['action' => 'interrupt', 'focus' => $event];
        }

        $plans = $this->planner->getActivePlans();
        $predictions = $this->predictor->predict('next_action', []);
        $decision = $this->reasoner->decide([$plans, $predictions]);
        $this->currentFocus = $decision['focus'] ?? 'idle';

        return $decision;
    }

    public function interrupt(string $event): void
    {
        $this->interruptions[] = $event;
    }

    public function pause(string $workflowId): void
    {
        $this->pausedWorkflows[$workflowId] = now();
    }

    public function resume(string $workflowId): void
    {
        unset($this->pausedWorkflows[$workflowId]);
    }

    public function prioritize(array $tasks): array
    {
        usort($tasks, fn($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));
        return $tasks;
    }

    public function getCurrentFocus(): ?string
    {
        return $this->currentFocus;
    }

    public function getStatus(): array
    {
        return [
            'current_focus' => $this->currentFocus,
            'interruptions' => $this->interruptions,
            'paused_workflows' => array_keys($this->pausedWorkflows),
        ];
    }
}
