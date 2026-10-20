<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxKirim extends Model
{
    protected $table = 'outbox_kirim';

    protected $fillable = [
        'event_id',
        'event_type',
        'routing_key',
        'payload',
        'status',
        'retry_count',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'retry_count' => 'integer',
        'sent_at' => 'datetime',
    ];
}
