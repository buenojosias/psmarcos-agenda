<?php

namespace App\Models;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes, HasUlids;
    
    protected $fillable = [
        'group_id',
        'name',
        'type',
        'recurrence_code',
        'starts_at',
        'ends_at',
        'status',
        'is_external',
        'is_public',
        'advertisable'
    ];

    protected $casts = [
        'type' => EventTypeEnum::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_external' => 'boolean',
        'is_public' => 'boolean',
        'status' => EventStatusEnum::class,
        'advertisable' => 'boolean'
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(PlaceReservation::class);
    }

    public function primaryPlace(): HasOne
    {
        return $this->hasOne(PlaceReservation::class)->where('is_primary', true);
    }
}
