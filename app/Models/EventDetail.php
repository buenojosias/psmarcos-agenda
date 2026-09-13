<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDetail extends Model
{
    protected $fillable = [
        'event_id',
        'subtitle',
        'description',
        'target_audience',
        'participation_instructions',
        'registration_required',
        'registration_url',
        'registration_deadline',
        'participation_cost',
        'contact_name',
        'contact_phone',
        'external_location_name',
        'external_location_address',
        'external_location_url',
    ];

    protected $casts = [
        'registration_required' => 'boolean',
        'registration_deadline' => 'date',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
