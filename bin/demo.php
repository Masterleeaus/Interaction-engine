<?php

declare(strict_types=1);

use TitanZero\Interaction\Authority\ApprovalSigner;
use TitanZero\Interaction\Authority\AuthorityLevel;
use TitanZero\Interaction\Authority\CapabilityPolicy;
use TitanZero\Interaction\Policy\PolicyEngine;

$packageRoot = dirname(__DIR__) . '/packages/policy-engine/src/';
spl_autoload_register(static function (string $class) use ($packageRoot): void {
    $prefix = 'TitanZero\\Interaction\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $path = $packageRoot . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$signer = new ApprovalSigner(bin2hex(random_bytes(32)));
$gate = new PolicyEngine($signer);
$gate->registerCapabilityPolicy(new CapabilityPolicy(
    capability: 'quotes.create',
    authority: AuthorityLevel::ApprovalRequired,
    requiredRoles: ['owner', 'manager'],
    numericLimits: ['total' => 25000],
    approvalTtlSeconds: 900,
));
$gate->registerPolicy('quotes.create', static function (array $payload): array|string {
    if (trim((string) ($payload['customer_id'] ?? '')) === '') {
        return 'A customer must be selected.';
    }
    if (!isset($payload['total']) || (float) $payload['total'] <= 0) {
        return 'The quote total must be greater than zero.';
    }
    return ['allowed' => true];
});

// This is an intentionally untrusted, deterministic model proposal. The proposal
// has no capability handler and cannot change state until the policy gate allows it.
$proposal = [
    'capability' => 'quotes.create',
    'payload' => [
        'customer_id' => 'customer-jenny',
        'customer_name' => 'Jenny',
        'service' => 'carpet cleaning',
        'total' => 450.00,
    ],
    'source' => 'model-proposal-fixture',
];
$context = ['tenant_id' => 'tenant-demo', 'actor_type' => 'agent', 'agent_id' => 'proposal-agent'];
$proposedPayload = $proposal['payload'] + ['_context' => $context];
$initialDecision = $gate->decide($proposal['capability'], $proposedPayload);

echo "Titan Zero Interaction Engine — governed quote demo\n";
echo "Proposal: Create a $450 carpet-cleaning quote for Jenny.\n";
echo 'Gate before approval: ' . ($initialDecision->allowed ? 'ALLOW' : 'DENY') . ' — ' . implode('; ', $initialDecision->reasons) . "\n";

if ($initialDecision->allowed) {
    fwrite(STDERR, "The demo expected the initial action to be denied.\n");
    exit(1);
}

// In production this call belongs behind the host's authenticated approval UI.
$grant = $signer->issueForPayload(
    'quotes.create', 'tenant-demo', 'owner-demo', ['owner'], $proposal['payload'], 600,
);
$approvedPayload = $proposal['payload'] + ['_context' => $context, '_approval' => $grant];
$approvedDecision = $gate->decide($proposal['capability'], $approvedPayload);
echo 'Owner approval: ' . ($approvedDecision->allowed ? 'VALID' : 'REJECTED') . "\n";

if (!$approvedDecision->allowed) {
    fwrite(STDERR, 'The approved action was rejected: ' . implode('; ', $approvedDecision->reasons) . "\n");
    exit(1);
}

// This in-memory handler demonstrates the guarded side effect without touching a live system.
$execution = static fn(array $payload): array => [
    'quote_id' => 'quote-demo-001',
    'customer' => $payload['customer_name'],
    'service' => $payload['service'],
    'total' => $payload['total'],
    'status' => 'draft',
];
$quote = $execution($approvedPayload);
$audit = [[
    'event' => 'capability_executed',
    'capability' => 'quotes.create',
    'tenant_id' => 'tenant-demo',
    'actor_id' => 'owner-demo',
    'quote_id' => $quote['quote_id'],
    'success' => true,
    'recorded_at' => gmdate(DATE_ATOM),
]];

echo "Execution: {$quote['quote_id']} created as a draft for {$quote['customer']} — AUD " . number_format($quote['total'], 2) . "\n";
echo 'Audit: ' . $audit[0]['event'] . ' recorded for ' . $audit[0]['quote_id'] . "\n";
echo 'Summary: denied before approval; approved; executed once; audit entries=' . count($audit) . "\n";
