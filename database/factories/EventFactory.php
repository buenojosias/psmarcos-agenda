<?php

namespace Database\Factories;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+6 months');
        $endsAt = (clone $startsAt)->modify('+'.fake()->numberBetween(60, 180).' minutes');

        return [
            'group_id' => Group::query()->inRandomOrder()->value('id'),
            'name' => ucfirst(fake()->words(3, true)),
            'type' => fake()->randomElement([
                EventTypeEnum::MEETING,
                EventTypeEnum::REHEARSAL,
                EventTypeEnum::PARTY,
                EventTypeEnum::FOOD,
                EventTypeEnum::COURSE,
                EventTypeEnum::OTHER,
            ]),
            'recurrence_code' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => fake()->randomElement([
                EventStatusEnum::PENDING,
                EventStatusEnum::CONFIRMED,
            ]),
            'is_external' => false,
            'is_public' => true,
            'advertisable' => fake()->boolean(35),
        ];
    }
}
