<?php

declare(strict_types=1);

use TitanZero\Interaction\Authority\ApprovalSigner;
use TitanZero\Interaction\Authority\AuthorityLevel;
use TitanZero\Interaction\Authority\CapabilityPolicy;
use TitanZero\Interaction\Policy\PolicyEngine;

$packageRoot = dirname(__DIR__) . '/src/';
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

$passed = 0;
$failed = 0;
$test = static function (string $name, callable $body) use (&$passed, &$failed): void {
    try {
        $body();
        echo "PASS {$name}\n";
        $passed++;
    } catch (Throwable $error) {
        echo "FAIL {$name}: {$error->getMessage()}\n";
        $failed++;
    }
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$secret = 'standalone-policy-test-secret-32-bytes-minimum';

$test('unknown capabilities fail closed', static function () use ($assert): void {
    $gate = new PolicyEngine();
    $assert(!$gate->decide('unknown.action', [])->allowed, 'Unknown action was allowed.');
});

$test('non-executable authority levels deny execution', static function () use ($assert): void {
    foreach ([AuthorityLevel::ObserveOnly, AuthorityLevel::RecommendOnly, AuthorityLevel::PrepareOnly] as $level) {
        $gate = new PolicyEngine();
        $gate->registerCapabilityPolicy(new CapabilityPolicy('quotes.create', $level));
        $assert(!$gate->decide('quotes.create', [])->allowed, $level->value . ' policy was allowed.');
    }
});

$test('user-only actions require a human identity and an allowed role', static function () use ($assert): void {
    $gate = new PolicyEngine();
    $gate->registerCapabilityPolicy(new CapabilityPolicy(
        'finance.payment.record', AuthorityLevel::UserOnly,
        requiredRoles: ['owner', 'finance'], freshAuthenticationSeconds: 300,
    ));
    $fresh = time();
    $assert($gate->decide('finance.payment.record', ['_context' => [
        'tenant_id' => 'tenant-a', 'actor_type' => 'human', 'user_id' => 'u-1', 'roles' => ['finance'], 'authenticated_at' => $fresh,
    ]])->allowed, 'Fresh finance actor was denied.');
    $assert(!$gate->decide('finance.payment.record', ['_context' => [
        'actor_type' => 'agent', 'user_id' => 'u-1', 'roles' => ['finance'], 'authenticated_at' => $fresh,
    ]])->allowed, 'Agent passed a user-only policy.');
    $assert(!$gate->decide('finance.payment.record', ['_context' => [
        'tenant_id' => 'tenant-a', 'actor_type' => 'human', 'user_id' => 'u-2', 'roles' => ['viewer'], 'authenticated_at' => $fresh,
    ]])->allowed, 'Wrong role passed a user-only policy.');
});

$test('approval is signed, tenant scoped, time limited, and action bound', static function () use ($assert, $secret): void {
    $signer = new ApprovalSigner($secret);
    $gate = new PolicyEngine($signer);
    $gate->registerCapabilityPolicy(new CapabilityPolicy(
        'quotes.create', AuthorityLevel::ApprovalRequired,
        requiredRoles: ['owner', 'manager'], numericLimits: ['total' => 25000], approvalTtlSeconds: 900,
    ));
    $action = ['customer_id' => 'jenny', 'total' => 480.00];
    $context = ['tenant_id' => 'tenant-a', 'actor_type' => 'agent'];
    $grant = $signer->issueForPayload('quotes.create', 'tenant-a', 'user-9', ['manager'], $action, 300);
    $assert($gate->decide('quotes.create', $action + ['_context' => $context, '_approval' => $grant])->allowed, 'Valid approval was denied.');
    $assert(!$gate->decide('quotes.create', $action + ['_context' => ['tenant_id' => 'tenant-b'], '_approval' => $grant])->allowed, 'Cross-tenant approval was allowed.');
    $changedAction = ['customer_id' => 'jenny', 'total' => 481.00, '_context' => $context, '_approval' => $grant];
    $assert(!$gate->decide('quotes.create', $changedAction)->allowed, 'Approval was reusable with a changed payload.');
    $tampered = $grant;
    $tampered['approved_by'] = 'attacker';
    $assert(!$gate->decide('quotes.create', $action + ['_context' => $context, '_approval' => $tampered])->allowed, 'Tampered approval was allowed.');
    $overLimit = ['customer_id' => 'jenny', 'total' => 26000.00];
    $expensiveGrant = $signer->issueForPayload('quotes.create', 'tenant-a', 'user-9', ['manager'], $overLimit, 300);
    $assert(!$gate->decide('quotes.create', $overLimit + ['_context' => $context, '_approval' => $expensiveGrant])->allowed, 'Over-limit quote was allowed.');
});

$test('approval expiration is checked against an injectable clock', static function () use ($assert, $secret): void {
    $signer = new ApprovalSigner($secret);
    $action = ['customer_id' => 'jenny', 'total' => 480.00];
    $grant = $signer->issueForPayload('quotes.create', 'tenant-a', 'user-9', ['manager'], $action, 5);
    $gate = new PolicyEngine($signer, static fn(): int => time() + 10);
    $gate->registerCapabilityPolicy(new CapabilityPolicy('quotes.create', AuthorityLevel::ApprovalRequired, requiredRoles: ['manager']));
    $assert(!$gate->decide('quotes.create', $action + ['_context' => ['tenant_id' => 'tenant-a'], '_approval' => $grant])->allowed, 'Expired approval was allowed.');
});

$test('delegated actions require a named actor, every scope, and respect limits', static function () use ($assert): void {
    $gate = new PolicyEngine();
    $gate->registerCapabilityPolicy(new CapabilityPolicy(
        'crm.customer.create', AuthorityLevel::DelegatedAutonomous,
        delegatedScopes: ['crm:customer:create', 'tenant:write'], numericLimits: ['records' => 1],
    ));
    $payload = ['records' => 1, '_context' => ['tenant_id' => 'tenant-a', 'actor_type' => 'agent', 'delegated_scopes' => ['crm:customer:create', 'tenant:write']]];
    $assert($gate->decide('crm.customer.create', $payload)->allowed, 'Properly scoped actor was denied.');
    $assert(!$gate->decide('crm.customer.create', ['records' => 1, '_context' => ['actor_type' => 'agent', 'delegated_scopes' => ['crm:customer:create']]])->allowed, 'Missing scope was allowed.');
    $assert(!$gate->decide('crm.customer.create', ['records' => 2, '_context' => $payload['_context']])->allowed, 'Limit breach was allowed.');
    $assert(!$gate->decide('crm.customer.create', ['records' => 1, '_context' => ['actor_type' => 'unknown']])->allowed, 'Unknown actor was allowed.');
});

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
