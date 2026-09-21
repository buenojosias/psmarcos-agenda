<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
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

    /** @return HasMany<Mass, $this> */
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

        return DB::transaction(function () use ($startsAt): Mass {
            self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            return $this->masses()->firstOrCreate(['starts_at' => $startsAt], [
                'community_id' => $this->community_id,
                'motivation'   => $this->motivation,
                'ends_at'      => $startsAt->addMinutes($this->duration_minutes),
            ]);
        });
    }

    /** @return list<array{starts_at: string, ends_at: string}> */
    public function occurrences(): array
    {
        if ($this->valid_from === null || $this->valid_until === null) {
            throw new InvalidArgumentException('A finite date interval is required to generate occurrences.');
        }

        $date        = CarbonImmutable::parse($this->valid_from)->max(CarbonImmutable::today());
        $date        = $date->addDays(($this->weekday - $date->dayOfWeek + 7) % 7);
        $occurrences = [];

        for (; $date->lte($this->valid_until); $date = $date->addWeek()) {
            if ($this->isValidForDate($date)) {
                $start         = CarbonImmutable::parse($date->toDateString().' '.$this->starts_at);
                $occurrences[] = [
                    'starts_at' => $start->toDateTimeString(),
                    'ends_at'   => $start->addMinutes($this->duration_minutes)->toDateTimeString(),
                ];
            }
        }

        return $occurrences;
    }

    public function isValidForDate(CarbonInterface $date): bool
    {
        $date = CarbonImmutable::parse($date->toDateString());

        return $this->is_active
            && $date->greaterThanOrEqualTo(CarbonImmutable::today())
            && $this->weekday === $date->dayOfWeek
            && ($this->valid_from === null || $this->valid_from->lessThanOrEqualTo($date))
            && ($this->valid_until === null || $this->valid_until->greaterThanOrEqualTo($date));
    }
}
