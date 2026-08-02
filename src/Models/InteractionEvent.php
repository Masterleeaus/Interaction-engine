<?php

declare(strict_types=1);

namespace TitanZero\Interaction\Models;

use Illuminate\Database\Eloquent\Model;

class InteractionEvent extends Model
{
    protected $table = 'interaction_events';

    protected $fillable = [
        'run_id',
        'event_type',
        'data',
        'occurred_at',
    ];

    protected $casts = [
        'data' => 'array',
        'occurred_at' => 'datetime',
    ];
}