<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Place extends Model
{
    protected $fillable = [
        'community_id',
        'main_place_id',
        'name',
    ];

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<Place, $this> */
    public function main(): BelongsTo
    {
        return $this->belongsTo(self::class, 'main_place_id');
    }

    public function subplaces(): HasMany
    {
        return $this->hasMany(self::class, 'main_place_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(PlaceReservation::class);
    }

    public function massCommunities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class, 'community_mass_place')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
