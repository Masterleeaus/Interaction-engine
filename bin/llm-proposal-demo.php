<?php

declare(strict_types=1);

use TitanZero\Interaction\AI\AIServiceInterface;
use TitanZero\Interaction\AI\OpenAIActionProposer;
use TitanZero\Interaction\AI\OpenAIService;
use TitanZero\Interaction\Authority\AuthorityLevel;
use TitanZero\Interaction\Authority\CapabilityPolicy;
use TitanZero\Interaction\Command\CommandBus;
use TitanZero\Interaction\Contracts\EventRecorderInterface;
use TitanZero\Interaction\Policy\PolicyEngine;

require dirname(__DIR__) . '/vendor/autoload.php';

$apiKey = (string) (getenv('INTERACTION_AI_API_KEY') ?: getenv('OPENAI_API_KEY') ?: '');
if ($apiKey === '') {
    fwrite(STDERR, "Set INTERACTION_AI_API_KEY or OPENAI_API_KEY to run the live proposal demo.\n");
    exit(2);
}
if (!class_exists('OpenAI')) {
    fwrite(STDERR, "Install the optional SDK with: composer require openai-php/client\n");
    exit(2);
}

$model = (string) (getenv('INTERACTION_AI_MODEL') ?: 'gpt-4o-mini');
$client = \OpenAI::factory()->withApiKey($apiKey)->make();
$ai = new OpenAIService($client, $model);
$proposer = new OpenAIActionProposer($ai, $model);
$proposal = $proposer->propose(
    'Create a draft carpet-cleaning quote for Jenny for AUD 450.',
    ['quotes.create'],
);

$policy = new PolicyEngine();
$policy->registerCapabilityPolicy(new CapabilityPolicy(
    'quotes.create', AuthorityLevel::ApprovalRequired,
    requiredRoles: ['owner', 'manager'], numericLimits: ['total' => 25000],
));
$events = new class implements EventRecorderInterface {
    public array $entries = [];
    public function record(string $eventType, array $data): void { $this->entries[] = ['type' => $eventType, 'data' => $data]; }
    public function getEvents(int $runId): array { return []; }
};
$handlerCalls = 0;
$commands = new CommandBus($policy, $events);
$commands->registerHandler('quotes.create', static function () use (&$handlerCalls): void { $handlerCalls++; });

echo "Live model proposal — no approval grant is provided\n";
echo "Model: {$proposal->model}\n";
echo "Capability: {$proposal->capability}\n";
echo 'Payload: ' . json_encode($proposal->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
echo "Rationale: {$proposal->rationale}\n";

try {
    $commands->dispatch($proposal->capability, $proposal->payload + [
        '_context' => [
            'tenant_id' => 'demo-tenant',
            'actor_type' => 'agent',
            'agent_id' => 'openai-proposal-demo',
        ],
    ]);
    fwrite(STDERR, "The policy gate unexpectedly allowed the unapproved proposal.\n");
    exit(1);
} catch (RuntimeException $error) {
    if (!str_starts_with($error->getMessage(), 'Policy denied:')) {
        throw $error;
    }
    echo "Policy gate: DENY — " . substr($error->getMessage(), strlen('Policy denied: ')) . "\n";
}

$denials = array_values(array_filter($events->entries, static fn(array $event): bool => $event['type'] === 'capability_denied'));
echo 'Handler calls: ' . $handlerCalls . "\n";
echo 'Denial audit events: ' . count($denials) . "\n";
exit($handlerCalls === 0 && count($denials) === 1 ? 0 : 1);
