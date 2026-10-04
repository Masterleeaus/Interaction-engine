# Titan Zero Policy Engine

A small, host independent PHP package for capability authority decisions. It requires PHP 8.2 and has no Laravel, database, cache, or AI dependency.

The gate defaults to deny when a capability has no policy. Registered policies can require a human actor, recent authentication, a signed approval tied to the exact action payload, delegated scopes, numeric limits, and additional domain rules. The package decides whether an action may execute; it does not execute the action itself.

## Run the package tests

```sh
php tests/run.php
```

## Example

```php
$signer = new TitanZero\Interaction\Authority\ApprovalSigner(
    getenv('INTERACTION_APPROVAL_SECRET') ?: throw new RuntimeException('Set a 32+ character approval secret.'),
);
$gate = new TitanZero\Interaction\Policy\PolicyEngine($signer);
$gate->registerCapabilityPolicy(new TitanZero\Interaction\Authority\CapabilityPolicy(
    capability: 'quotes.create',
    authority: TitanZero\Interaction\Authority\AuthorityLevel::ApprovalRequired,
    requiredRoles: ['owner', 'manager'],
    numericLimits: ['total' => 25000],
    approvalTtlSeconds: 900,
));

$action = ['customer_id' => 'customer-42', 'total' => 480.00];
$grant = $signer->issueForPayload(
    'quotes.create', 'tenant-7', 'user-3', ['manager'], $action, 600,
);
$action['_context'] = ['tenant_id' => 'tenant-7', 'actor_type' => 'agent'];
$action['_approval'] = $grant;

if (!$gate->decide('quotes.create', $action)->allowed) {
    throw new RuntimeException('The action was denied.');
}
// Call the application's registered capability handler only after this gate.
```

Only trusted server-side approval code should call `ApprovalSigner::issueForPayload`. Do not sign approval claims supplied directly by a browser, model, or device.

## Decision rules

- Unknown capabilities, observe-only, recommend-only, and prepare-only policies deny execution.
- Approval tokens are HMAC-SHA256 signed, tenant and capability scoped, time limited, and by default bound to the action payload.
- User-only actions require an identified human, an allowed role when configured, and recent authentication when configured.
- Non-human delegated actors must identify themselves and hold every required delegated scope.
- Configured numeric limits fail closed when exceeded.

The package trusts `_context` as server-built input. The host must authenticate the actor, resolve tenant and roles, and construct this context itself; copying identity or authentication claims from a browser or model payload defeats the boundary. The package does not replace the host's authentication, tenancy, role assignment, approval UX, audit storage, or transaction handling.
