<?php

declare(strict_types=1);

namespace TitanZero\Engines\Learning\Implementations;

use TitanZero\Engines\Learning\Contracts\PreferenceLearningEngineInterface;
use Illuminate\Support\Facades\DB;

class PreferenceLearningEngine implements PreferenceLearningEngineInterface
{
    public function recordPreference(int $userId, string $key, $value): void
    {
        DB::table('user_preferences')->updateOrInsert(
            ['user_id' => $userId, 'key' => $key],
            ['value' => json_encode($value), 'updated_at' => now()]
        );
    }

    public function getPreference(int $userId, string $key)
    {
        $record = DB::table('user_preferences')
            ->where('user_id', $userId)
            ->where('key', $key)
            ->first();
        return $record ? json_decode($record->value) : null;
    }

    public function getAllPreferences(int $userId): array
    {
        return DB::table('user_preferences')
            ->where('user_id', $userId)
            ->get()
            ->mapWithKeys(fn($r) => [$r->key => json_decode($r->value)])
            ->all();
    }
}
