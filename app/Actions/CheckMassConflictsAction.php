<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Place;
use Carbon\CarbonImmutable;
use App\Models\PlaceReservation;
use Illuminate\Database\Eloquent\Collection;

class CheckMassConflictsAction
{
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
        $reservations = PlaceReservation::query()->whereIn('place_id', $places->modelKeys())
            ->where('reserved_from', '<', $periods->max('to'))
            ->where('reserved_to', '>', $periods->min('from'))
            ->get(['place_id', 'reserved_from', 'reserved_to']);
        $names = $places->pluck('name', 'id');

        return $periods->map(function (array $period) use ($reservations, $names): array {
            return [
                'date'   => $period['date'],
                'places' => $reservations->filter(fn (PlaceReservation $reservation): bool => $reservation->reserved_from->lt($period['to']) && $reservation->reserved_to->gt($period['from']))
                    ->pluck('place_id')->unique()->map(fn (int $id): string => $names[$id])->values()->all(),
            ];
        })->filter(fn (array $conflict): bool => $conflict['places'] !== [])->values()->all();
    }
}
