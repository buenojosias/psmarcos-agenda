<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MassSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'community_id',
        'weekday',
        'starts_at',
        'duration_minutes',
        'motivation',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $attributes = [
        'duration_minutes' => 60,
        'is_active'        => true,
    ];

    protected $casts = [
        'weekday'          => 'integer',
        'duration_minutes' => 'integer',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
        'is_active'        => 'boolean',
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function masses(): HasMany
    {
        return $this->hasMany(Mass::class);
    }

    public function generateMassForDate(CarbonInterface $date): Mass
    {
        if (! $this->isValidForDate($date)) {
            throw new InvalidArgumentException('The mass schedule is not valid for the given date.');
        }

        $startsAt = CarbonImmutable::parse($date->toDateString().' '.$this->starts_at);

        return $this->masses()->create([
            'community_id' => $this->community_id,
            'motivation'   => $this->motivation,
            'starts_at'    => $startsAt,
            'ends_at'      => $startsAt->addMinutes($this->duration_minutes),
        ]);
    }

    public function isValidForDate(CarbonInterface $date): bool
    {
        $date = CarbonImmutable::parse($date->toDateString());

        return $this->is_active
            && $this->weekday === $date->dayOfWeek
            && ($this->valid_from === null || $this->valid_from->lessThanOrEqualTo($date))
            && ($this->valid_until === null || $this->valid_until->greaterThanOrEqualTo($date));
    }
}
