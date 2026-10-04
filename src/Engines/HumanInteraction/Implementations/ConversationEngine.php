<?php

declare(strict_types=1);

namespace TitanZero\Engines\HumanInteraction\Implementations;

use TitanZero\Engines\HumanInteraction\Contracts\ConversationEngineInterface;
class ConversationEngine implements ConversationEngineInterface
{
    private array $history = [];
    private array $context = [];
    private bool $active = false;
    private DialogueEngine $dialogue;

    public function __construct()
    {
        $this->dialogue = new DialogueEngine();
    }

    public function start(array $context): void
    {
        $this->context = $context;
        $this->dialogue->reset();
        $this->history = [['role' => 'system', 'content' => 'Conversation started', 'context_keys' => array_keys($context)]];
        $this->active = true;
    }

    public function process(string $input): string
    {
        if (!$this->active) {
            $this->start($this->context);
        }
        $this->history[] = ['role' => 'user', 'content' => $input];
        $response = $this->generateResponse($input);
        $this->history[] = ['role' => 'assistant', 'content' => $response];
        return $response;
    }

    private function generateResponse(string $input): string
    {
        return $this->dialogue->process($input);
    }

    public function end(): void
    {
        $this->history[] = ['role' => 'system', 'content' => 'Conversation ended'];
        $this->active = false;
    }

    public function getHistory(): array
    {
        return $this->history;
    }
}
