<?php

declare(strict_types=1);

namespace TitanZero\Interaction\LocalIntelligence\Storage;

final class NullLocalIntelligenceMemoryStore implements LocalIntelligenceMemoryStoreInterface
{
    public function get(string $tenantId, ?string $userId, ?string $deviceId, string $type, string $key): ?array
    {
        return null;
    }

    public function all(string $tenantId, ?string $userId, ?string $deviceId, string $type, ?string $keyPrefix = null): array
    {
        return [];
    }

    public function put(
        string $tenantId,
        ?string $userId,
        ?string $deviceId,
        string $type,
        string $key,
        array $value,
        ?float $confidence = null
    ): void {
        // Intentional no-op.
    }
}
