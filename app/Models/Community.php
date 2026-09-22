<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Community extends Model
{
    protected $fillable = [
        'name',
        'abbreviation',
        'alias',
        'address',
    ];

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    public function massSchedules(): HasMany
    {
        return $this->hasMany(MassSchedule::class);
    }

    public function masses(): HasMany
    {
        return $this->hasMany(Mass::class);
    }

    public function massPlaces(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'community_mass_place')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
