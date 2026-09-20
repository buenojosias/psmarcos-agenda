<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Community;
use App\Models\MassSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MassSchedule>
 */
class MassScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::query()->inRandomOrder()->value('id') ?? Community::create([
                'name'         => 'Comunidade '.fake()->unique()->word(),
                'abbreviation' => mb_strtoupper(fake()->unique()->lexify('??')),
                'alias'        => fake()->unique()->slug(2),
            ])->id,
            'weekday'          => fake()->numberBetween(0, 6),
            'starts_at'        => fake()->randomElement(['07:00:00', '08:30:00', '10:00:00', '19:30:00']),
            'duration_minutes' => 60,
            'motivation'       => fake()->randomElement(['Missa Dominical', 'Missa com Novena', 'Missa votiva', null]),
            'valid_from'       => null,
            'valid_until'      => null,
            'is_active'        => true,
        ];
    }
}
