<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Place;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use App\Models\PlaceReservation;
use Illuminate\Database\Eloquent\Collection;

class CheckPlaceAvailabilityAction
{
    /**
     * @param  Collection<int, Place>  $places
     * @param  array<int, array{before_hours: float|int|string, after_hours: float|int|string}>  $placeHours
     * @return list<array{
     *     requested_place: array{id: int, name: string},
     *     requested_reserved_from: string,
     *     requested_reserved_to: string,
     *     conflicts: list<array{
     *         reserved_place: array{id: int, name: string},
     *         reserved_from: string,
     *         reserved_to: string
     *     }>
     * }>
     */
    public function handle(
        CarbonInterface|string $startsAt,
        CarbonInterface|string $endsAt,
        Collection $places,
        array $placeHours,
    ): array {
        if ($places->isEmpty()) {
            return [];
        }

        $places->loadMissing(['main:id,name', 'subplaces:id,main_place_id,name']);

        $startsAt = $this->toImmutable($startsAt);
        $endsAt   = $this->toImmutable($endsAt);

        $requests = $places->map(function (Place $place) use ($startsAt, $endsAt, $placeHours): array {
            $hours = $placeHours[$place->id] ?? ['before_hours' => 0, 'after_hours' => 0];

            return [
                'place'          => $place,
                'checked_places' => collect([$place])
                    ->concat($place->subplaces)
                    ->when($place->main !== null, fn ($checkedPlaces) => $checkedPlaces->push($place->main))
                    ->keyBy('id'),
                'reserved_from' => $startsAt->subMinutes((int) round((float) $hours['before_hours'] * 60)),
                'reserved_to'   => $endsAt->addMinutes((int) round((float) $hours['after_hours'] * 60)),
            ];
        });

        $checkedPlaces     = $requests->pluck('checked_places')->flatten(1)->keyBy('id');
        $checkedPlaceNames = $checkedPlaces
            ->mapWithKeys(fn (Place $place): array => [$place->id => $place->name])
            ->all();
        $reservations = PlaceReservation::query()
            ->whereIn('place_id', $checkedPlaces->keys())
            ->where('reserved_from', '<', $requests->max('reserved_to'))
            ->where('reserved_to', '>', $requests->min('reserved_from'))
            ->orderBy('reserved_from')
            ->orderBy('id')
            ->get(['id', 'place_id', 'reserved_from', 'reserved_to']);

        return $requests->map(function (array $request) use ($reservations, $checkedPlaceNames): array {
            $conflicts = $reservations
                ->filter(fn (PlaceReservation $reservation): bool => $request['checked_places']->has($reservation->place_id)
                    && $reservation->reserved_from->lt($request['reserved_to'])
                    && $reservation->reserved_to->gt($request['reserved_from']))
                ->map(function (PlaceReservation $reservation) use ($checkedPlaceNames): array {
                    return [
                        'reserved_place' => [
                            'id'   => $reservation->place_id,
                            'name' => $checkedPlaceNames[$reservation->place_id],
                        ],
                        'reserved_from' => $reservation->reserved_from->format('Y-m-d H:i:s'),
                        'reserved_to'   => $reservation->reserved_to->format('Y-m-d H:i:s'),
                    ];
                })->values()->all();

            return [
                'requested_place' => [
                    'id'   => $request['place']->id,
                    'name' => $request['place']->name,
                ],
                'requested_reserved_from' => $request['reserved_from']->format('Y-m-d H:i:s'),
                'requested_reserved_to'   => $request['reserved_to']->format('Y-m-d H:i:s'),
                'conflicts'               => $conflicts,
            ];
        })->filter(fn (array $request): bool => $request['conflicts'] !== [])->values()->all();
    }

    private function toImmutable(CarbonInterface|string $date): CarbonImmutable
    {
        return $date instanceof CarbonInterface
            ? CarbonImmutable::instance($date)
            : CarbonImmutable::parse($date);
    }
}
