<?php

namespace App\Models;

use App\Enums\EventLogActionEnum;
use App\Enums\EventStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLog extends Model
{
    protected $fillable = [
        'event_id',
        'user_id',
        'operation_code',
        'action',
        'from_status',
        'to_status',
        'changes'
    ];

    protected $casts = [
        'action' => EventLogActionEnum::class,
        'from_status' => EventStatusEnum::class,
        'to_status' => EventStatusEnum::class,
        'changes' => 'array',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
