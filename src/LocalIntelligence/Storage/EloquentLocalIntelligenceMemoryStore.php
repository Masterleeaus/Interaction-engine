<?php

declare(strict_types=1);

namespace TitanZero\Interaction\LocalIntelligence\Storage;

use TitanZero\Interaction\Models\LocalIntelligenceMemory;

/**
 * This is the wiring that was missing from the source archive: the
 * local_intelligence_memories migration and LocalIntelligenceMemory
 * model both existed already, correctly matched to each other, but
 * nothing anywhere in src/LocalIntelligence/ ever loaded from or saved
 * to them — every "learning" engine held its state in a plain PHP array
 * on a request-scoped singleton, which is empty again on the very next
 * request. This class is that missing connection.
 */
final class EloquentLocalIntelligenceMemoryStore implements LocalIntelligenceMemoryStoreInterface
{
    public function get(string $tenantId, ?string $userId, ?string $deviceId, string $type, string $key): ?array
    {
        $row = $this->query($tenantId, $userId, $deviceId, $type)
            ->where('memory_key', $key)
            ->first();

        return $row === null ? null : $this->toArray($row);
    }

    public function all(string $tenantId, ?string $userId, ?string $deviceId, string $type, ?string $keyPrefix = null): array
    {
        $query = $this->query($tenantId, $userId, $deviceId, $type);
        if ($keyPrefix !== null && $keyPrefix !== '') {
            $query->where('memory_key', 'like', addcslashes($keyPrefix, '%_') . '%');
        }

        return $query->get()->map(fn (LocalIntelligenceMemory $row): array => $this->toArray($row))->all();
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
        $row = $this->query($tenantId, $userId, $deviceId, $type)
            ->where('memory_key', $key)
            ->first();

        if ($row === null) {
            LocalIntelligenceMemory::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'device_id' => $deviceId,
                'memory_type' => $type,
                'memory_key' => $key,
                'value' => $value,
                'confidence' => $confidence ?? 0.5,
                'frequency' => 1,
                'last_observed_at' => now(),
            ]);
            return;
        }

        $row->value = $value;
        $row->frequency = $row->frequency + 1;
        $row->last_observed_at = now();
        if ($confidence !== null) {
            $row->confidence = $confidence;
        }
        $row->save();
    }

    private function query(string $tenantId, ?string $userId, ?string $deviceId, string $type)
    {
        $query = LocalIntelligenceMemory::query()
            ->where('tenant_id', $tenantId)
            ->where('memory_type', $type);

        // NOTE: where('col', null) compiles to "col = NULL", which never
        // matches in SQL — must use whereNull() explicitly for the
        // "no user/device scope" case.
        $userId === null ? $query->whereNull('user_id') : $query->where('user_id', $userId);
        $deviceId === null ? $query->whereNull('device_id') : $query->where('device_id', $deviceId);

        return $query;
    }

    private function toArray(LocalIntelligenceMemory $row): array
    {
        return [
            'key' => $row->memory_key,
            'value' => (array) $row->value,
            'confidence' => (float) $row->confidence,
            'frequency' => (int) $row->frequency,
        ];
    }
}
