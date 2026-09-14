<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'place_id',
        'reserved_from',
        'reserved_to',
        'is_primary'
    ];

    protected $casts = [
        'reserved_from' => 'datetime',
        'reserved_to' => 'datetime',
        'is_primary' => 'boolean'
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
