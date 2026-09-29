<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mass;
use App\Models\Event;
use App\Models\Place;
use App\Models\PlaceReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceReservationFactory extends Factory
{
    protected $model = PlaceReservation::class;

    public function definition(): array
    {
        $event = Event::query()->inRandomOrder()->first() ?? Event::factory()->create();
        [$reservedFrom, $reservedTo] = $this->reservationInterval(
            $event->starts_at->copy(),
            $event->ends_at->copy(),
        );

        return [
            'event_id'      => $event->id,
            'mass_id'       => null,
            'place_id'      => Place::query()->inRandomOrder()->value('id'),
            'reserved_from' => $reservedFrom,
            'reserved_to'   => $reservedTo,
            'is_primary'    => false,
        ];
    }

    public function forMass(?Mass $mass = null): static
    {
        return $this->state(function () use ($mass): array {
            $mass ??= Mass::query()->inRandomOrder()->first() ?? Mass::factory()->create();
            [$reservedFrom, $reservedTo] = $this->reservationInterval(
                $mass->starts_at->copy(),
                $mass->ends_at->copy(),
            );

            return [
                'event_id'      => null,
                'mass_id'       => $mass->id,
                'reserved_from' => $reservedFrom,
                'reserved_to'   => $reservedTo,
            ];
        });
    }

    /** @return array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface} */
    private function reservationInterval($startsAt, $endsAt): array
    {
        // A maioria das reservas ganha margens suficientes para ultrapassar uma hora.
        $beforeMinutes = fake()->randomElement([15, 30, 30, 45, 60]);
        $afterMinutes = fake()->randomElement([15, 30, 45, 45, 60]);

        $reservedFrom = $startsAt->copy()->subMinutes($beforeMinutes);
        $reservedTo = $endsAt->copy()->addMinutes($afterMinutes);

        // Salvaguarda para factories usadas com eventos/missas customizados muito curtos.
        if ($reservedFrom->diffInMinutes($reservedTo) < 30) {
            $reservedTo = $reservedFrom->copy()->addMinutes(30);
        }

        return [$reservedFrom, $reservedTo];
    }
}
