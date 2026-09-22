<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
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
