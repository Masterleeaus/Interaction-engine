<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Models;

use Illuminate\Database\Eloquent\Model;

final class LocalIntelligenceMemory extends Model
{
    protected $table = 'local_intelligence_memories';

    protected $fillable = [
        'tenant_id', 'user_id', 'device_id', 'memory_type', 'memory_key', 'value',
        'confidence', 'frequency', 'last_observed_at', 'expires_at',
    ];

    protected $casts = [
        'value' => 'array',
        'confidence' => 'float',
        'frequency' => 'integer',
        'last_observed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
