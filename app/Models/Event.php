<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory, SoftDeletes;

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
        'advertisable',
        'reservation_hold_until',
    ];

    protected $casts = [
        'type'         => EventTypeEnum::class,
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'is_external'  => 'boolean',
        'is_public'    => 'boolean',
        'status'       => EventStatusEnum::class,
        'advertisable' => 'boolean',
        'reservation_hold_until' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isMemberOnly()) {
            return $query;
        }

        return $query->where(fn (Builder $query): Builder => $query
            ->where('status', EventStatusEnum::CONFIRMED)
            ->orWhere(fn (Builder $query): Builder => $query->fromUserGroups($user)));
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeFromUserGroups(Builder $query, User $user): Builder
    {
        return $query->whereIn('group_id', $user->groups()->select('groups.id'));
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>', now());
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where('ends_at', '<=', now());
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeAwaitingReview(Builder $query): Builder
    {
        return $query->whereIn('status', [EventStatusEnum::PENDING, EventStatusEnum::RESCHEDULED]);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(EventDetail::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(EventNote::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EventLog::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(PlaceReservation::class);
    }

    public function primaryReservation(): HasOne
    {
        return $this->hasOne(PlaceReservation::class)->where('is_primary', true);
    }
}
