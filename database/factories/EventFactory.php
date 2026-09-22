<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Group;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+6 months');
        $endsAt   = (clone $startsAt)->modify('+'.fake()->numberBetween(60, 180).' minutes');

        return [
            'group_id'     => Group::query()->inRandomOrder()->value('id'),
            'community_id' => fn (array $attributes): ?int => $attributes['is_external']
                ? null
                : (Community::query()->inRandomOrder()->value('id') ?? Community::create([
                    'name'         => fake()->unique()->company(),
                    'abbreviation' => fake()->unique()->lexify('???'),
                    'alias'        => fake()->unique()->slug(2),
                ])->id),
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
            'starts_at'       => $startsAt,
            'ends_at'         => $endsAt,
            'status'          => fake()->randomElement([
                EventStatusEnum::PENDING,
                EventStatusEnum::CONFIRMED,
            ]),
            'is_external'  => false,
            'is_public'    => true,
            'advertisable' => fake()->boolean(35),
        ];
    }
}
