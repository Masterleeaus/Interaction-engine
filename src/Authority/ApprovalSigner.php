<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Authority;

final class ApprovalSigner
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 16) {
            throw new \InvalidArgumentException('Approval signing secret must be at least 16 characters.');
        }
    }

    public function issue(string $capability, string $tenantId, string $approvedBy, array $approverRoles, int $ttlSeconds = 600, ?string $subjectId = null): array
    {
        $issued = time();
        $grant = [
            'capability' => $capability,
            'tenant_id' => $tenantId,
            'approved_by' => $approvedBy,
            'approver_roles' => array_values(array_unique(array_map('strval', $approverRoles))),
            'approved_at' => $issued,
            'expires_at' => $issued + max(1, $ttlSeconds),
            'subject_id' => $subjectId,
            'nonce' => bin2hex(random_bytes(16)),
        ];
        $grant['signature'] = hash_hmac('sha256', $this->canonical($grant), $this->secret);
        return $grant;
    }

    public function verify(array $grant): bool
    {
        $signature = (string) ($grant['signature'] ?? '');
        if ($signature === '') {
            return false;
        }
        unset($grant['signature']);
        return hash_equals(hash_hmac('sha256', $this->canonical($grant), $this->secret), $signature);
    }

    private function canonical(array $grant): string
    {
        ksort($grant);
        return json_encode($grant, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
