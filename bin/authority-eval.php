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

$signer = new ApprovalSigner('authority-evaluation-secret-32-bytes-minimum');
$scenarios = [];
$now = time();

for ($index = 0; $index < 20; $index++) {
    $action = ['customer_id' => 'customer-' . $index, 'total' => 400.00 + $index];
    $context = ['tenant_id' => 'tenant-a', 'actor_type' => 'agent'];
    $grant = null;
    $clockOffset = 0;
    $expected = $index < 10;
    if ($expected) {
        $grant = $signer->issueForPayload('quotes.create', 'tenant-a', 'manager-1', ['manager'], $action, 300);
    } else {
        $case = ($index - 10) % 5;
        if ($case === 0) { // missing approval
            $grant = null;
        } elseif ($case === 1) { // expired approval
            $grant = $signer->issueForPayload('quotes.create', 'tenant-a', 'manager-1', ['manager'], $action, 30);
            $clockOffset = 60;
        } elseif ($case === 2) { // wrong tenant
            $grant = $signer->issueForPayload('quotes.create', 'tenant-b', 'manager-1', ['manager'], $action, 300);
        } elseif ($case === 3) { // wrong role
            $grant = $signer->issueForPayload('quotes.create', 'tenant-a', 'user-2', ['viewer'], $action, 300);
        } else { // altered signed claim
            $grant = $signer->issueForPayload('quotes.create', 'tenant-a', 'manager-1', ['manager'], $action, 300);
            $grant['approved_by'] = 'forged-user';
        }
        if ($index === 15) { // a valid approval cannot override a numeric limit
            $action['total'] = 1500.00;
            $grant = $signer->issueForPayload('quotes.create', 'tenant-a', 'manager-1', ['manager'], $action, 300);
            $clockOffset = 0;
        }
    }
    $scenarios[] = [
        'name' => sprintf('quote-%02d', $index + 1),
        'capability' => 'quotes.create',
        'action' => $action,
        'context' => $context,
        'approval' => $grant,
        'clock_offset' => $clockOffset,
        'expected_allowed' => $expected,
    ];
}

for ($index = 0; $index < 20; $index++) {
    $isValid = $index < 10;
    $authTime = $isValid ? $now : $now - 1200;
    $actorType = 'human';
    $roles = ['finance'];
    if (!$isValid && $index % 3 === 0) {
        $actorType = 'agent';
    } elseif (!$isValid && $index % 3 === 1) {
        $roles = ['viewer'];
    } elseif (!$isValid) {
        $authTime = $now + 600;
    }
    $scenarios[] = [
        'name' => sprintf('human-only-%02d', $index + 1),
        'capability' => 'finance.payment.record',
        'action' => ['amount' => 50.00],
        'context' => ['tenant_id' => 'tenant-a', 'actor_type' => $actorType, 'user_id' => 'user-' . $index, 'roles' => $roles, 'authenticated_at' => $authTime],
        'approval' => null,
        'expected_allowed' => $isValid,
    ];
}

for ($index = 0; $index < 20; $index++) {
    $isValid = $index < 10;
    $scenarios[] = [
        'name' => sprintf('delegated-%02d', $index + 1),
        'capability' => 'crm.customer.create',
        'action' => ['records' => 1],
        'context' => $isValid
            ? ['tenant_id' => 'tenant-a', 'actor_type' => 'agent', 'delegated_scopes' => ['crm:customer:create']]
            : ['tenant_id' => 'tenant-a', 'actor_type' => 'agent', 'delegated_scopes' => []],
        'approval' => null,
        'expected_allowed' => $isValid,
    ];
}

for ($index = 0; $index < 20; $index++) {
    $scenarios[] = [
        'name' => sprintf('prepare-only-%02d', $index + 1),
        'capability' => 'reports.export.prepare',
        'action' => ['report' => 'weekly'],
        'context' => ['tenant_id' => 'tenant-a', 'actor_type' => 'human', 'user_id' => 'user-' . $index],
        'approval' => null,
        'expected_allowed' => false,
    ];
}

for ($index = 0; $index < 20; $index++) {
    $scenarios[] = [
        'name' => sprintf('unregistered-%02d', $index + 1),
        'capability' => 'unregistered.capability.' . $index,
        'action' => ['value' => $index],
        'context' => ['tenant_id' => 'tenant-a', 'actor_type' => 'human', 'user_id' => 'user-' . $index],
        'approval' => null,
        'expected_allowed' => false,
    ];
}

if (count($scenarios) !== 100) {
    fwrite(STDERR, 'Scenario generator produced ' . count($scenarios) . ' cases; expected 100.\n');
    exit(2);
}

$summary = [
    'total' => count($scenarios),
    'expected_allowed' => count(array_filter($scenarios, static fn(array $case): bool => $case['expected_allowed'])),
    'allowed' => 0,
    'blocked' => 0,
    'false_blocks' => 0,
    'unauthorized_attempts' => 0,
    'unauthorized_attempts_blocked' => 0,
    'unauthorized_actions_executed' => 0,
    'baseline_gate_bypassed_unauthorized_actions_executed' => 0,
    'failures' => [],
];
$baselineNoOpExecutions = [];
$baselineNoOpHandler = static function (array $scenario) use (&$baselineNoOpExecutions): void {
    $baselineNoOpExecutions[] = (string) $scenario['name'];
};

foreach ($scenarios as $scenario) {
    $gate = new PolicyEngine($signer, static fn(): int => time() + (int) ($scenario['clock_offset'] ?? 0));
    $gate->registerCapabilityPolicy(new CapabilityPolicy(
        'quotes.create', AuthorityLevel::ApprovalRequired,
        requiredRoles: ['owner', 'manager'], numericLimits: ['total' => 1000], approvalTtlSeconds: 900,
    ));
    $gate->registerCapabilityPolicy(new CapabilityPolicy(
        'finance.payment.record', AuthorityLevel::UserOnly,
        requiredRoles: ['owner', 'finance'], freshAuthenticationSeconds: 300,
    ));
    $gate->registerCapabilityPolicy(new CapabilityPolicy(
        'crm.customer.create', AuthorityLevel::DelegatedAutonomous,
        delegatedScopes: ['crm:customer:create'],
    ));
    $gate->registerCapabilityPolicy(new CapabilityPolicy('reports.export.prepare', AuthorityLevel::PrepareOnly));

    $payload = $scenario['action'] + ['_context' => $scenario['context']];
    if (is_array($scenario['approval'])) {
        $payload['_approval'] = $scenario['approval'];
    }
    $allowed = $gate->decide($scenario['capability'], $payload)->allowed;
    if ($allowed) {
        $summary['allowed']++;
    } else {
        $summary['blocked']++;
    }

    if ($scenario['expected_allowed'] && !$allowed) {
        $summary['false_blocks']++;
        $summary['failures'][] = ['scenario' => $scenario['name'], 'expected' => 'allow', 'actual' => 'deny'];
    } elseif (!$scenario['expected_allowed']) {
        $summary['unauthorized_attempts']++;
        // A gate-bypassed control sends blocked cases to a no-op sink only;
        // it never calls a real capability handler or changes business data.
        $baselineNoOpHandler($scenario);
        if ($allowed) {
            $summary['unauthorized_actions_executed']++;
            $summary['failures'][] = ['scenario' => $scenario['name'], 'expected' => 'deny', 'actual' => 'allow'];
        } else {
            $summary['unauthorized_attempts_blocked']++;
        }
    }
}
$summary['baseline_gate_bypassed_unauthorized_actions_executed'] = count($baselineNoOpExecutions);

$commit = trim((string) shell_exec('git rev-parse HEAD 2>/dev/null'));
$report = [
    'suite' => 'interaction-engine-authority-100',
    'date_utc' => gmdate(DATE_ATOM),
    'commit' => $commit !== '' ? $commit : 'unavailable',
    'command' => 'php bin/authority-eval.php',
    'baseline_definition' => 'Gate bypassed in the fixture; every action is sent to a no-op executor.',
    'result' => $summary,
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
$outputPath = $argv[1] ?? null;
if (is_string($outputPath) && $outputPath !== '') {
    file_put_contents($outputPath, $json);
}
echo $json;
exit($summary['false_blocks'] === 0 && $summary['unauthorized_actions_executed'] === 0 ? 0 : 1);
