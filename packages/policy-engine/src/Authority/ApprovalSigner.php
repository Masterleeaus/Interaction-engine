<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Authority;

final class ApprovalSigner
{
    public function __construct(private readonly string $secret)
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('Approval signing secret must contain at least 32 characters.');
        }
    }

    public function issue(string $capability, string $tenantId, string $approvedBy, array $approverRoles, int $ttlSeconds = 600, ?string $subjectId = null, ?array $boundPayload = null): array
    {
        if (trim($capability) === '' || trim($tenantId) === '' || trim($approvedBy) === '') {
            throw new \InvalidArgumentException('Capability, tenant and approver identity are required to issue an approval.');
        }
        if ($ttlSeconds < 1) {
            throw new \InvalidArgumentException('Approval TTL must be at least one second.');
        }

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
        if ($boundPayload !== null) {
            $grant['payload_sha256'] = $this->payloadDigest($boundPayload);
        }
        $grant['signature'] = hash_hmac('sha256', $this->canonical($grant), $this->secret);
        return $grant;
    }

    /** Issue a grant bound to the exact action data that the policy gate will evaluate. */
    public function issueForPayload(string $capability, string $tenantId, string $approvedBy, array $approverRoles, array $payload, int $ttlSeconds = 600, ?string $subjectId = null): array
    {
        return $this->issue($capability, $tenantId, $approvedBy, $approverRoles, $ttlSeconds, $subjectId, $payload);
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

    public function verifyForPayload(array $grant, array $payload): bool
    {
        $digest = (string) ($grant['payload_sha256'] ?? '');
        return $digest !== '' && $this->verify($grant)
            && hash_equals($digest, $this->payloadDigest($payload));
    }

    private function payloadDigest(array $payload): string
    {
        unset($payload['_approval'], $payload['_context']);
        return hash('sha256', $this->canonical($payload));
    }

    private function canonical(array $grant): string
    {
        return json_encode($this->sortAssociativeKeys($grant), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function sortAssociativeKeys(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortAssociativeKeys($item);
            }
        }
        return $value;
    }
}
