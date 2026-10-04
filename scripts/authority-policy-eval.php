<?php

declare(strict_types=1);

use TitanZero\Interaction\Authority\ApprovalSigner;
use TitanZero\Interaction\Authority\AuthorityLevel;
use TitanZero\Interaction\Authority\CapabilityPolicy;
use TitanZero\Interaction\Policy\PolicyEngine;

$root = dirname(__DIR__);
$scenarioPath = $root . '/evaluations/authority-policy/scenarios.json';
$scenarioDocument = json_decode((string) file_get_contents($scenarioPath), true, 512, JSON_THROW_ON_ERROR);
$secret = 'interaction-policy-eval-secret-2026';

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'TitanZero\\Engines\\' => $root . '/src/Engines/',
        'TitanZero\\Interaction\\' => [$root . '/packages/policy-engine/src/', $root . '/src/'],
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            foreach ((array) $base as $directory) {
                $path = $directory . str_replace('\\', '/', $relative) . '.php';
                if (is_file($path)) {
                    require_once $path;
                    return;
                }
            }
            return;
        }
    }
});

$signGrant = static function (array $grant) use ($secret): array {
    unset($grant['signature']);
    ksort($grant);
    $canonical = json_encode($grant, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $grant['signature'] = hash_hmac('sha256', $canonical, $secret);
    return $grant;
};

mt_srand((int) $scenarioDocument['seed']);
$results = [];
$expectedAllowed = 0;
$expectedDenied = 0;
$wronglyDenied = 0;
$unauthorizedAllowed = 0;
$wrongTenantAttempts = 0;
$wrongTenantAllowed = 0;

foreach ($scenarioDocument['cases'] as $case) {
    $expected = (bool) $case['expected_allowed'];
    $expected ? $expectedAllowed++ : $expectedDenied++;

    if (($case['category'] ?? '') === 'tenant-boundary') {
        $wrongTenantAttempts++;
    }

    try {
        $signerEnabled = (bool) ($case['signer_enabled'] ?? true);
        $signer = $signerEnabled ? new ApprovalSigner($secret) : null;
        $engine = new PolicyEngine($signer);

        if (($case['registered'] ?? true) !== false) {
            $authority = AuthorityLevel::from((string) $case['authority']);
            $engine->registerCapabilityPolicy(new CapabilityPolicy(
                capability: (string) $case['capability'],
                authority: $authority,
                requiredRoles: (array) ($case['required_roles'] ?? []),
                delegatedScopes: (array) ($case['delegated_scopes'] ?? []),
                numericLimits: (array) ($case['numeric_limits'] ?? []),
                freshAuthenticationSeconds: (int) ($case['fresh_authentication_seconds'] ?? 0),
                approvalTtlSeconds: (int) ($case['approval_ttl_seconds'] ?? 900),
            ));
        }

        if (isset($case['custom_policy'])) {
            $customPolicy = $case['custom_policy'];
            $engine->registerPolicy((string) $case['capability'], static function (array $payload) use ($customPolicy): mixed {
                return match ($customPolicy['type']) {
                    'allow' => ['allowed' => true],
                    'false' => false,
                    'reason' => (string) $customPolicy['value'],
                    'structured_denial' => [
                        'allowed' => false,
                        'reason' => (string) $customPolicy['value'],
                    ],
                    default => throw new RuntimeException('Unknown custom policy fixture.'),
                };
            });
        }

        $context = (array) ($case['context'] ?? []);
        if (array_key_exists('authentication_age_seconds', $case)) {
            $context['authenticated_at'] = time() - (int) $case['authentication_age_seconds'];
        }

        $payload = (array) ($case['payload'] ?? []);
        $payload['_context'] = $context;

        if (isset($case['approval']) && ($case['approval']['mode'] ?? 'missing') !== 'missing' && $signer instanceof ApprovalSigner) {
            $approvalCase = $case['approval'];
            $mode = (string) $approvalCase['mode'];
            $approvalCapability = $mode === 'wrong_capability'
                ? 'finance.refund.create'
                : (string) $case['capability'];
            $approvalTenant = $mode === 'wrong_tenant'
                ? 'tenant-b'
                : (string) ($context['tenant_id'] ?? '');
            $approvalTtl = $mode === 'over_ttl'
                ? max((int) ($case['approval_ttl_seconds'] ?? 900) + 1, (int) ($approvalCase['issue_ttl_seconds'] ?? 901))
                : (int) ($approvalCase['issue_ttl_seconds'] ?? 300);

            $approvedBy = (string) ($approvalCase['approved_by'] ?? 'manager-1');
            $approval = $signer->issueForPayload(
                capability: $approvalCapability,
                tenantId: $approvalTenant,
                approvedBy: trim($approvedBy) === '' ? 'evaluation-placeholder' : $approvedBy,
                approverRoles: (array) ($approvalCase['roles'] ?? ['manager']),
                payload: $payload,
                ttlSeconds: $approvalTtl,
            );

            if ($mode === 'expired') {
                $approval['expires_at'] = time() - 1;
                $approval = $signGrant($approval);
            } elseif ($mode === 'tampered') {
                $approval['approver_roles'] = ['owner'];
            } elseif (trim($approvedBy) === '') {
                $approval['approved_by'] = '';
                $approval = $signGrant($approval);
            }

            $payload['_approval'] = $approval;
        }

        $decision = $engine->decide((string) $case['capability'], $payload);
        $actual = $decision->allowed;
        $reason = $decision->reasons[0] ?? null;
    } catch (Throwable $error) {
        $actual = null;
        $reason = 'Evaluator exception: ' . $error->getMessage();
    }

    $passed = $actual === $expected;
    if (!$passed && $actual === true && !$expected) {
        $unauthorizedAllowed++;
        if (($case['category'] ?? '') === 'tenant-boundary') {
            $wrongTenantAllowed++;
        }
    } elseif (!$passed && $actual === false && $expected) {
        $wronglyDenied++;
    }

    $results[] = [
        'id' => (string) $case['id'],
        'category' => (string) ($case['category'] ?? 'uncategorized'),
        'expected_allowed' => $expected,
        'actual_allowed' => $actual,
        'passed' => $passed,
        'reason' => $reason,
    ];
}

$failed = count(array_filter($results, static fn (array $result): bool => !$result['passed']));
$commit = getenv('GITHUB_SHA') ?: 'local-uncommitted';
$scenarioHash = hash_file('sha256', $scenarioPath);
$evaluatedAt = gmdate('Y-m-d\TH:i:s\Z');

$report = [
    'title' => (string) $scenarioDocument['title'],
    'evaluated_at_utc' => $evaluatedAt,
    'evaluated_commit' => $commit,
    'scenario_seed' => (int) $scenarioDocument['seed'],
    'scenario_count' => count($results),
    'scenario_sha256' => $scenarioHash,
    'php_version' => PHP_VERSION,
    'baseline' => [
        'name' => (string) $scenarioDocument['baseline']['name'],
        'description' => (string) $scenarioDocument['baseline']['behavior'],
        'unauthorized_cases_allowed' => $expectedDenied,
        'unauthorized_cases_total' => $expectedDenied,
    ],
    'metrics' => [
        'unauthorized_actions_allowed' => $unauthorizedAllowed,
        'unauthorized_actions_total' => $expectedDenied,
        'valid_actions_wrongly_denied' => $wronglyDenied,
        'valid_actions_total' => $expectedAllowed,
        'wrong_tenant_approvals_allowed' => $wrongTenantAllowed,
        'wrong_tenant_attempts_total' => $wrongTenantAttempts,
        'scenario_failures' => $failed,
    ],
    'cases' => $results,
];

$outputDir = $root . '/eval-results';
if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Could not create evaluation output directory.');
}

$jsonPath = $outputDir . '/authority-policy-latest.json';
$markdownPath = $outputDir . '/authority-policy-latest.md';
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);

$lines = [
    '# Authority Policy Evaluation',
    '',
    '- Evaluated: ' . $evaluatedAt,
    '- Evaluator commit: ' . $commit,
    '- Scenarios: ' . count($results) . ' (seed ' . (int) $scenarioDocument['seed'] . ')',
    '- Scenario SHA-256: ' . $scenarioHash,
    '- PHP: ' . PHP_VERSION,
    '- Baseline: **' . $expectedDenied . '/' . $expectedDenied . '** blocked cases would pass through an allow-all bypass.',
    '',
    '| Metric | Result |',
    '| --- | ---: |',
    '| Unauthorized actions allowed | ' . $unauthorizedAllowed . ' / ' . $expectedDenied . ' |',
    '| Valid actions wrongly denied | ' . $wronglyDenied . ' / ' . $expectedAllowed . ' |',
    '| Wrong-tenant approvals allowed | ' . $wrongTenantAllowed . ' / ' . $wrongTenantAttempts . ' |',
    '| Scenario mismatches | ' . $failed . ' / ' . count($results) . ' |',
    '',
    'The baseline is a deliberately simple allow-all bypass control, not a competing product. This evaluation calls the repository PolicyEngine directly; it does not test a host adapter, persistence, or downstream execution.',
    '',
];

if ($failed > 0) {
    $lines[] = '## Failed scenarios';
    $lines[] = '';
    $lines[] = '| Scenario | Expected allow | Actual allow | Reason |';
    $lines[] = '| --- | ---: | ---: | --- |';
    foreach ($results as $result) {
        if (!$result['passed']) {
            $actualText = $result['actual_allowed'] === null ? 'error' : ($result['actual_allowed'] ? 'yes' : 'no');
            $lines[] = '| ' . $result['id'] . ' | ' . ($result['expected_allowed'] ? 'yes' : 'no')
                . ' | ' . $actualText . ' | ' . str_replace('|', '\\|', (string) $result['reason']) . ' |';
        }
    }
    $lines[] = '';
}

$lines[] = '## Scenario outcomes';
$lines[] = '';
$lines[] = '| Scenario | Category | Expected | Actual | Result |';
$lines[] = '| --- | --- | ---: | ---: | --- |';
foreach ($results as $result) {
    $actualText = $result['actual_allowed'] === null ? 'error' : ($result['actual_allowed'] ? 'allow' : 'deny');
    $lines[] = '| ' . $result['id'] . ' | ' . $result['category'] . ' | '
        . ($result['expected_allowed'] ? 'allow' : 'deny') . ' | ' . $actualText . ' | '
        . ($result['passed'] ? 'PASS' : 'FAIL') . ' |';
}
$lines[] = '';
file_put_contents($markdownPath, implode(PHP_EOL, $lines));

echo 'Authority policy evaluation: ' . count($results) . ' cases, ' . $failed . ' failures.' . PHP_EOL;
echo 'Unauthorized allowed: ' . $unauthorizedAllowed . '/' . $expectedDenied . '; valid wrongly denied: '
    . $wronglyDenied . '/' . $expectedAllowed . '; wrong-tenant approvals allowed: '
    . $wrongTenantAllowed . '/' . $wrongTenantAttempts . '.' . PHP_EOL;
echo 'Wrote ' . $jsonPath . PHP_EOL;
echo 'Wrote ' . $markdownPath . PHP_EOL;

exit($failed === 0 ? 0 : 1);
