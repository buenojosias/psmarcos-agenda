<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Community extends Model
{
     protected $fillable = [
        'name',
        'abbreiviation',
        'alias',
        'address',
    ];

    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }
}
