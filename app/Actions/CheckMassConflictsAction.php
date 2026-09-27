<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Place;
use Carbon\CarbonImmutable;

use Illuminate\Database\Eloquent\Collection;

class CheckMassConflictsAction
{
    public function __construct(private PlaceAvailabilityAction $availability) {}

    /**
     * @param  Collection<int, Place>  $places
     * @param  list<array{starts_at: string, ends_at: string}>  $occurrences
     * @return list<array{date: string, places: list<string>}>
     */
    public function handle(Collection $places, array $occurrences): array
    {
        if ($occurrences === []) {
            return [];
        }

        $periods = collect($occurrences)->map(fn (array $occurrence): array => [
            'date' => CarbonImmutable::parse($occurrence['starts_at'])->toDateString(),
            'from' => CarbonImmutable::parse($occurrence['starts_at'])->subMinutes(30),
            'to'   => CarbonImmutable::parse($occurrence['ends_at'])->addMinutes(30),
        ]);
        $tree         = Place::query()->whereIn('community_id', $places->pluck('community_id'))->get();
        $relatedIds   = $places->mapWithKeys(fn (Place $place): array => [$place->id => $this->availability->relatedPlaceIds($place, $tree)]);
        $reservations = $this->availability->reservations(
            $relatedIds->flatten()->unique()->values()->all(), $periods->min('from'), $periods->max('to'),
        )->get(['place_id', 'reserved_from', 'reserved_to']);

        return $periods->map(function (array $period) use ($reservations, $places, $relatedIds): array {
            return [
                'date'   => $period['date'],
                'places' => $places->filter(fn (Place $place): bool => $reservations->contains(
                    fn ($reservation): bool => in_array($reservation->place_id, $relatedIds[$place->id], true)
                        && $reservation->reserved_from->lt($period['to']) && $reservation->reserved_to->gt($period['from']),
                ))->pluck('name')->values()->all(),
            ];
        })->filter(fn (array $conflict): bool => $conflict['places'] !== [])->values()->all();
    }
}
