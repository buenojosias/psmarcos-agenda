<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'community_id',
        'name',
        'type',
        'slug',
        'description',
    ];

    protected $casts = [
        'type' => GroupTypeEnum::class
    ];

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_coordinator');
    }
}
