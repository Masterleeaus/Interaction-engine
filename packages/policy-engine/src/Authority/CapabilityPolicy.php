<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Authority;

final readonly class CapabilityPolicy
{
    public function __construct(
        public string $capability,
        public AuthorityLevel $authority,
        public array $requiredRoles = [],
        public array $delegatedScopes = [],
        public array $numericLimits = [],
        public int $freshAuthenticationSeconds = 0,
        public int $approvalTtlSeconds = 900,
        public bool $learningOutcomeAllowed = true,
        public bool $learningContentAllowed = false,
        public bool $requirePayloadBinding = true,
    ) {
        if (trim($capability) === '') {
            throw new \InvalidArgumentException('Capability name is required.');
        }
        if ($freshAuthenticationSeconds < 0) {
            throw new \InvalidArgumentException('Fresh-authentication window cannot be negative.');
        }
        if ($approvalTtlSeconds < 1) {
            throw new \InvalidArgumentException('Approval TTL must be at least one second.');
        }
        foreach (array_merge($requiredRoles, $delegatedScopes) as $value) {
            if (!is_string($value) || trim($value) === '') {
                throw new \InvalidArgumentException('Roles and delegated scopes must be non-empty strings.');
            }
        }
        foreach ($numericLimits as $field => $maximum) {
            if (trim((string) $field) === '' || !is_numeric($maximum) || !is_finite((float) $maximum) || (float) $maximum < 0) {
                throw new \InvalidArgumentException('Numeric limits must use named fields and finite, non-negative numeric values.');
            }
        }
    }
}
