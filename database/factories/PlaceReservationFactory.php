<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Place;
use App\Models\PlaceReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceReservationFactory extends Factory
{
    protected $model = PlaceReservation::class;

    public function definition(): array
    {
        $event = Event::query()->inRandomOrder()->first();

        return [
            'event_id' => $event?->id,
            'place_id' => Place::query()->inRandomOrder()->value('id'),
            'reserved_from' => $event?->starts_at?->copy()->subMinutes(fake()->numberBetween(30, 120)),
            'reserved_to' => $event?->ends_at?->copy()->addMinutes(fake()->numberBetween(30, 90)),
            'is_primary' => false,
        ];
    }
}
