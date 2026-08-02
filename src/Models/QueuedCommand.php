<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Models;

use Illuminate\Database\Eloquent\Model;

class QueuedCommand extends Model
{
    protected $table = 'queued_commands';

    protected $fillable = [
        'capability',
        'payload',
        'metadata',
        'status', // pending, synced, failed
        'attempts',
        'error',
        'created_at',
        'synced_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'attempts' => 'integer',
        'created_at' => 'datetime',
        'synced_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}