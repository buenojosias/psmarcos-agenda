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

        return [
            'event_id'      => $event?->id,
            'mass_id'       => null,
            'place_id'      => Place::query()->inRandomOrder()->value('id'),
            'reserved_from' => $event->starts_at->copy()->floorMinutes(15)->subMinutes(fake()->numberBetween(2, 8) * 15),
            'reserved_to'   => $event->ends_at->copy()->ceilMinutes(15)->addMinutes(fake()->numberBetween(2, 6) * 15),
            'is_primary'    => false,
        ];
    }

    public function forMass(?Mass $mass = null): static
    {
        return $this->state(function () use ($mass): array {
            $mass ??= Mass::query()->inRandomOrder()->first() ?? Mass::factory()->create();

            return [
                'event_id'      => null,
                'mass_id'       => $mass->id,
                'reserved_from' => $mass->starts_at->copy()->floorMinutes(15)->subMinutes(fake()->numberBetween(2, 8) * 15),
                'reserved_to'   => $mass->ends_at->copy()->ceilMinutes(15)->addMinutes(fake()->numberBetween(2, 6) * 15),
            ];
        });
    }
}
