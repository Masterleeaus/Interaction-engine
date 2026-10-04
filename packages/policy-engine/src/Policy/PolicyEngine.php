<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Policy;

use Closure;
use TitanZero\Interaction\Authority\AuthorityDecision;
use TitanZero\Interaction\Authority\AuthorityLevel;
use TitanZero\Interaction\Authority\ApprovalSigner;
use TitanZero\Interaction\Authority\CapabilityPolicy;
use TitanZero\Interaction\Contracts\PolicyEngineInterface;

class PolicyEngine implements PolicyEngineInterface
{
    private readonly ?ApprovalSigner $approvalSigner;
    private readonly Closure $clock;

    public function __construct(?ApprovalSigner $approvalSigner = null, ?callable $clock = null)
    {
        $this->approvalSigner = $approvalSigner;
        $this->clock = $clock === null ? static fn(): int => time() : Closure::fromCallable($clock);
    }

    private array $policies = [];
    private array $capabilityPolicies = [];
    private array $reasons = [];

    public function registerCapabilityPolicy(CapabilityPolicy $policy): void
    {
        $this->capabilityPolicies[$policy->capability] = $policy;
    }

    public function registerPolicy(string $capability, callable $policy): void
    {
        $this->policies[$capability][] = $policy;
    }

    public function decide(string $capability, array $payload): AuthorityDecision
    {
        $this->reasons = [];
        $definition = $this->capabilityPolicies[$capability] ?? null;
        if (!$definition instanceof CapabilityPolicy) {
            return $this->deny($capability, AuthorityLevel::PrepareOnly, 'No authority policy is registered for this capability.');
        }

        $context = (array) ($payload['_context'] ?? []);
        $actorType = (string) ($context['actor_type'] ?? 'unknown');
        $roles = array_values(array_map('strval', (array) ($context['roles'] ?? [])));
        $now = ($this->clock)();

        if ($definition->authority === AuthorityLevel::ObserveOnly || $definition->authority === AuthorityLevel::RecommendOnly || $definition->authority === AuthorityLevel::PrepareOnly) {
            return $this->deny($capability, $definition->authority, 'This capability cannot be executed at its configured authority level.');
        }

        if ($definition->authority === AuthorityLevel::UserOnly) {
            if (!$this->hasIdentity($context['tenant_id'] ?? null)) {
                return $this->deny($capability, $definition->authority, 'A tenant scope is required for executable actions.');
            }
            if ($actorType !== 'human' || !$this->hasIdentity($context['user_id'] ?? null)) {
                return $this->deny($capability, $definition->authority, 'Authenticated human context is required for this action.');
            }
            if (!$this->hasAnyRole($roles, $definition->requiredRoles)) {
                return $this->deny($capability, $definition->authority, 'The user does not hold a required role.');
            }
            if ($definition->freshAuthenticationSeconds > 0) {
                $authenticatedAt = (int) ($context['authenticated_at'] ?? 0);
                if ($authenticatedAt <= 0 || $authenticatedAt > $now || ($now - $authenticatedAt) > $definition->freshAuthenticationSeconds) {
                    return $this->deny($capability, $definition->authority, 'Fresh authentication is required.');
                }
            }
        }

        if ($definition->authority === AuthorityLevel::ApprovalRequired) {
            $approval = (array) ($payload['_approval'] ?? []);
            $validSignature = $this->approvalSigner instanceof ApprovalSigner
                && ($definition->requirePayloadBinding
                    ? $this->approvalSigner->verifyForPayload($approval, $payload)
                    : $this->approvalSigner->verify($approval));
            $approvedAt = (int) ($approval['approved_at'] ?? 0);
            $expiresAt = (int) ($approval['expires_at'] ?? 0);
            $tenantId = $this->hasIdentity($context['tenant_id'] ?? null) ? (string) $context['tenant_id'] : '';
            $valid = $validSignature
                && ($approval['capability'] ?? null) === $capability
                && !empty($approval['approved_by'])
                && $approvedAt > 0
                && $approvedAt <= $now
                && $expiresAt > $now
                && $expiresAt > $approvedAt
                && ($expiresAt - $approvedAt) <= $definition->approvalTtlSeconds
                && $tenantId !== ''
                && hash_equals($tenantId, (string) ($approval['tenant_id'] ?? ''))
                && $this->approvalSubjectMatches($approval, $payload, $context);
            $approverRoles = array_values(array_map('strval', (array) ($approval['approver_roles'] ?? [])));
            if (!$valid || !$this->hasAnyRole($approverRoles, $definition->requiredRoles)) {
                return $this->deny($capability, $definition->authority, 'A valid, scoped and unexpired human approval is required.');
            }
        }

        if ($definition->authority === AuthorityLevel::DelegatedAutonomous) {
            if (!$this->hasIdentity($context['tenant_id'] ?? null)) {
                return $this->deny($capability, $definition->authority, 'A tenant scope is required for executable actions.');
            }
            if ($actorType === 'human') {
                if (!$this->hasIdentity($context['user_id'] ?? null) || !$this->hasAnyRole($roles, $definition->requiredRoles)) {
                    return $this->deny($capability, $definition->authority, 'A verified, authorised user must perform this action.');
                }
            } else {
                if ($actorType === 'unknown' || $actorType === '') {
                    return $this->deny($capability, $definition->authority, 'An identified actor is required.');
                }
                $scopes = array_values(array_map('strval', (array) ($context['delegated_scopes'] ?? [])));
                foreach ($definition->delegatedScopes as $scope) {
                    if (!in_array((string) $scope, $scopes, true)) {
                        return $this->deny($capability, $definition->authority, "Missing delegated scope: {$scope}.");
                    }
                }
            }
        }

        foreach ($definition->numericLimits as $field => $maximum) {
            if (!array_key_exists($field, $payload) || !is_numeric($payload[$field]) || !is_finite((float) $payload[$field])) {
                return $this->deny($capability, $definition->authority, "{$field} must be a finite numeric value.");
            }
            if ((float) $payload[$field] > (float) $maximum) {
                return $this->deny($capability, $definition->authority, "{$field} exceeds delegated limit.");
            }
        }

        foreach ($this->policies[$capability] ?? [] as $policy) {
            $result = $policy($payload);
            if (is_string($result)) {
                return $this->deny($capability, $definition->authority, $result);
            }
            if ($result === false || $result === null) {
                return $this->deny($capability, $definition->authority, 'Policy failed.');
            }
            if ($result === true) {
                continue;
            }
            if (is_array($result) && ($result['allowed'] ?? false) === true) {
                continue;
            }
            return $this->deny($capability, $definition->authority, is_array($result) ? (string) ($result['reason'] ?? 'Policy failed.') : 'Policy returned an unsupported result.');
        }

        return new AuthorityDecision(true, $capability, $definition->authority);
    }

    public function evaluate(string $capability, array $payload): bool
    {
        return $this->decide($capability, $payload)->allowed;
    }

    public function getReasons(): array
    {
        return $this->reasons;
    }

    private function deny(string $capability, AuthorityLevel $authority, string $reason): AuthorityDecision
    {
        $this->reasons = [$reason];
        return AuthorityDecision::deny($capability, $authority, $reason);
    }

    private function hasAnyRole(array $actual, array $required): bool
    {
        return $required === [] || array_intersect($actual, $required) !== [];
    }

    private function hasIdentity(mixed $identity): bool
    {
        return (is_string($identity) || is_int($identity)) && trim((string) $identity) !== '';
    }

    private function approvalSubjectMatches(array $approval, array $payload, array $context): bool
    {
        $approvedSubject = (string) ($approval['subject_id'] ?? '');
        if ($approvedSubject === '') {
            return true;
        }
        $actionSubject = (string) ($payload['subject_id'] ?? $context['subject_id'] ?? '');
        return $actionSubject !== '' && hash_equals($approvedSubject, $actionSubject);
    }
}
