<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mass extends Model
{
    use HasFactory;

    protected $fillable = [
        'mass_schedule_id',
        'community_id',
        'motivation',
        'starts_at',
        'ends_at',
        'canceled_at',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MassSchedule::class, 'mass_schedule_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(PlaceReservation::class);
    }

    public function primaryReservation(): HasOne
    {
        return $this->hasOne(PlaceReservation::class)->where('is_primary', true);
    }

    /**
     * @param  Builder<Mass>  $query
     * @return Builder<Mass>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isMemberOnly()) {
            return $query;
        }

        return $query->whereNull('canceled_at');
    }

    /**
     * @param  Builder<Mass>  $query
     * @return Builder<Mass>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>', now());
    }

    protected static function booted(): void
    {
        static::created(function (Mass $mass): void {
            $mass->createDefaultReservations();
        });
    }

    private function createDefaultReservations(): void
    {
        $this->community->massPlaces()
            ->select('places.id')
            ->get()
            ->each(function (Place $place): void {
                $this->reservations()->create([
                    'place_id'      => $place->id,
                    'reserved_from' => $this->starts_at->copy()->subMinutes(30),
                    'reserved_to'   => $this->ends_at->copy()->addMinutes(30),
                    'is_primary'    => (bool) $place->pivot->is_primary,
                ]);
            });
    }
}
