<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Implementations;

use TitanZero\Engines\HumanInteraction\Contracts\DialogueEngineInterface;

class DialogueEngine implements DialogueEngineInterface
{
    private array $state = [];

    public function process(string $input): string
    {
        $text = trim($input);
        $turn = (int) ($this->state['turn_count'] ?? 0) + 1;
        $this->state['turn_count'] = $turn;
        $this->state['last_input'] = $text;
        if ($text === '') {
            $this->state['status'] = 'needs_input';
            return 'Tell me what you would like to do, and I will help prepare the next step.';
        }

        if (($this->state['awaiting_confirmation'] ?? false) === true && in_array(strtolower($text), ['yes', 'y', 'no', 'n'], true)) {
            $confirmed = in_array(strtolower($text), ['yes', 'y'], true);
            $this->state['awaiting_confirmation'] = false;
            $this->state['status'] = $confirmed ? 'proposal_confirmed' : 'proposal_rejected';
            return $confirmed
                ? 'Your confirmation was noted. The proposal still must pass the system permission and approval checks before execution.'
                : 'Understood. I have discarded the pending proposal.';
        }

        $understanding = (new \TitanZero\Interaction\LocalIntelligence\Language\LocalLanguageEngine())->understand($text);
        $this->state['intent'] = $understanding['intent'];
        $this->state['confidence'] = $understanding['confidence'];
        $this->state['entities'] = $understanding['entities'];
        if ($understanding['intent'] === 'unknown') {
            $this->state['status'] = 'needs_clarification';
            return 'What would you like to do? I can help prepare a quote, schedule a job, create an invoice, or record a payment.';
        }

        $requirements = match ($understanding['intent']) {
            'create_quote' => ['customer', 'service'],
            'schedule_job' => ['customer', 'relative_date'],
            'create_invoice', 'record_payment', 'complete_job' => ['customer'],
            default => [],
        };
        $missing = array_values(array_filter($requirements, static fn(string $key): bool => empty($understanding['entities'][$key])));
        if ($missing !== []) {
            $this->state['status'] = 'needs_clarification';
            $this->state['missing_entities'] = $missing;
            $this->state['awaiting_confirmation'] = false;
            return 'I can prepare this, but I still need: ' . implode(' and ', $missing) . '.';
        }

        $this->state['status'] = 'awaiting_confirmation';
        $this->state['awaiting_confirmation'] = true;
        $this->state['pending_proposal'] = ['intent' => $understanding['intent'], 'entities' => $understanding['entities']];
        $summary = implode(', ', array_map(static fn(string $key, mixed $value): string => $key . ': ' . (string) $value, array_keys($understanding['entities']), $understanding['entities']));
        return 'I can prepare a ' . str_replace('_', ' ', $understanding['intent']) . ' using ' . $summary . '. Shall I submit this proposal for the required checks?';
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function reset(): void
    {
        $this->state = [];
    }
}
