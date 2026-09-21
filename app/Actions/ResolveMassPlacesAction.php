<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Place;
use Illuminate\Database\Eloquent\Collection;

class ResolveMassPlacesAction
{
    public const array NAMES = ['Nave', 'Sacristia', 'Estacionamento'];

    /** @return Collection<int, Place> */
    public function handle(int $communityId): Collection
    {
        $places = Place::query()->where('community_id', $communityId)
            ->whereIn('name', self::NAMES)->orderBy('id')->get(['id', 'name']);

        return $places->unique('name')->values();
    }
}
