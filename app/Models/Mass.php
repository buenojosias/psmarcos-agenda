<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Actions\ResolveMassPlacesAction;
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

    /**
     * @param  Builder<Mass>  $query
     * @return Builder<Mass>
     */
    public function scopeWithReservationConflict(Builder $query): Builder
    {
        return $query->withExists(['reservations as has_reservation_conflict' => function (Builder $reservations): void {
            $reservations->whereHas('place', fn (Builder $places): Builder => $places
                ->whereIn('name', ResolveMassPlacesAction::NAMES)
                ->whereColumn('places.community_id', 'masses.community_id'))
                ->whereExists(function (\Illuminate\Database\Query\Builder $overlap): void {
                    $overlap->selectRaw('1')->from('place_reservations as conflicting')
                        ->whereColumn('conflicting.place_id', 'place_reservations.place_id')
                        ->whereColumn('conflicting.id', '!=', 'place_reservations.id')
                        ->whereColumn('conflicting.reserved_from', '<', 'place_reservations.reserved_to')
                        ->whereColumn('conflicting.reserved_to', '>', 'place_reservations.reserved_from');
                });
        }]);
    }

    protected static function booted(): void
    {
        static::creating(function (Mass $mass): void {
            $mass->setRelation('reservationPlaces', app(ResolveMassPlacesAction::class)->handle((int) $mass->community_id));
        });

        static::created(function (Mass $mass): void {
            $mass->createDefaultReservations();
        });
    }

    private function createDefaultReservations(): void
    {
        $this->getRelation('reservationPlaces')
            ->each(function (Place $place): void {
                $this->reservations()->create([
                    'place_id'      => $place->id,
                    'reserved_from' => $this->starts_at->copy()->subMinutes(30),
                    'reserved_to'   => $this->ends_at->copy()->addMinutes(30),
                    'is_primary'    => $place->name === 'Nave',
                ]);
            });
        $this->unsetRelation('reservationPlaces');
    }
}
