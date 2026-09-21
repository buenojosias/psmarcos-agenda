<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mass;
use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mass>
 */
class MassFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Mass $mass): void {
            foreach (['Nave', 'Sacristia', 'Estacionamento'] as $name) {
                $mass->community->places()->firstOrCreate(['name' => $name]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+6 months');
        $endsAt   = (clone $startsAt)->modify('+60 minutes');

        return [
            'mass_schedule_id' => null,
            'community_id'     => Community::query()->inRandomOrder()->value('id') ?? Community::create([
                'name'         => 'Comunidade '.fake()->unique()->word(),
                'abbreviation' => mb_strtoupper(fake()->unique()->lexify('??')),
                'alias'        => fake()->unique()->slug(2),
            ])->id,
            'motivation'  => fake()->randomElement(['Missa Dominical', 'Missa com Novena', 'Missa votiva', null]),
            'starts_at'   => $startsAt,
            'ends_at'     => $endsAt,
            'canceled_at' => null,
        ];
    }
}
