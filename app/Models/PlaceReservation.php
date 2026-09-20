<?php

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlaceReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'mass_id',
        'place_id',
        'reserved_from',
        'reserved_to',
        'is_primary',
    ];

    protected $casts = [
        'reserved_from' => 'datetime',
        'reserved_to'   => 'datetime',
        'is_primary'    => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function mass(): BelongsTo
    {
        return $this->belongsTo(Mass::class);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    protected static function booted(): void
    {
        static::saving(function (PlaceReservation $reservation): void {
            if (($reservation->event_id === null) === ($reservation->mass_id === null)) {
                throw new InvalidArgumentException('A place reservation must belong to exactly one event or mass.');
            }
        });
    }
}
