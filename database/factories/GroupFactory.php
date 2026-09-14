<?php

namespace Database\Factories;

use App\Enums\GroupTypeEnum;
use App\Models\Community;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'community_id' => fake()->boolean(70)
                ? Community::query()->inRandomOrder()->value('id')
                : null,
            'name' => $name,
            'type' => fake()->randomElement(GroupTypeEnum::cases()),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional(0.7)->sentence(12),
            'logo' => null,
        ];
    }
}
