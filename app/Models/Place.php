<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    protected $fillable = [
        'community_id',
        'main_place_id',
        'name',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function main(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'main_place_id');
    }

    public function subplaces(): HasMany
    {
        return $this->hasMany(Place::class, 'main_place_id');
    }
}
