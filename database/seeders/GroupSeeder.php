<?php

namespace Database\Seeders;

use App\Enums\GroupTypeEnum;
use App\Models\Community;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'Pastoral Familiar', 'type' => GroupTypeEnum::PASTORAL],
            ['name' => 'Pastoral da Liturgia', 'type' => GroupTypeEnum::PASTORAL],
            ['name' => 'Pastoral da Comunicação', 'type' => GroupTypeEnum::PASTORAL],
            ['name' => 'Legião de Maria', 'type' => GroupTypeEnum::MOVEMENT],
            ['name' => 'Ministros Extraordinários da Comunhão', 'type' => GroupTypeEnum::MINISTRY],
            ['name' => 'Conselho Pastoral Paroquial', 'type' => GroupTypeEnum::COUNCIL],
            ['name' => 'Grupo de Jovens', 'type' => GroupTypeEnum::GROUP],
            ['name' => 'Coral Paroquial', 'type' => GroupTypeEnum::SERVICE],
            ['name' => 'Curso de Informática', 'type' => GroupTypeEnum::COURSE],
            ['name' => 'Curso de Música', 'type' => GroupTypeEnum::COURSE],
        ];

        $communityIds = Community::query()->pluck('id');

        foreach ($groups as $data) {
            Group::factory()->create([
                ...$data,
                'community_id' => fake()->boolean(70) && $communityIds->isNotEmpty()
                    ? $communityIds->random()
                    : null,
                'slug' => Str::slug($data['name']),
                'description' => fake()->sentence(12),
            ]);
        }
    }
}
