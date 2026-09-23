<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Group;
use App\Models\Community;
use Illuminate\Support\Str;
use App\Enums\GroupTypeEnum;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $matrizId = Community::query()->where('name', 'Matriz São Marcos')->firstOrFail()->id;
        $beatoId  = Community::query()->where('name', 'Capela Beato Giacomo Cusmano')->firstOrFail()->id;

        $groups = [
            ['name' => 'Pastoral Familiar', 'type' => GroupTypeEnum::PASTORAL],
            ['name' => 'Pastoral da Liturgia', 'type' => GroupTypeEnum::PASTORAL, 'community_id' => $matrizId],
            ['name' => 'Pastoral da Comunicação', 'type' => GroupTypeEnum::PASTORAL, 'community_id' => $matrizId],
            ['name' => 'Legião de Maria', 'type' => GroupTypeEnum::MOVEMENT],
            ['name' => 'Ministros Extraordinários da Comunhão', 'type' => GroupTypeEnum::MINISTRY],
            ['name' => 'Conselho Pastoral Paroquial', 'type' => GroupTypeEnum::COUNCIL, 'community_id' => $matrizId],
            ['name' => 'Grupo de Jovens do Beato', 'type' => GroupTypeEnum::GROUP, 'community_id' => $beatoId],
            ['name' => 'Movimento de Irmãos', 'type' => GroupTypeEnum::MOVEMENT],
            ['name' => 'Coral Doce Canto', 'type' => GroupTypeEnum::GROUP, 'community_id' => $matrizId],
        ];

        $communityIds = Community::query()->pluck('id');
        $memberships  = [
            'Pastoral da Comunicação' => [
                'josias@email.com' => false,
                'pascom@email.com' => true,
            ],
            'Conselho Pastoral Paroquial' => [
                'cpp@email.com' => true,
            ],
            'Coral Doce Canto' => [
                'josias@email.com' => true,
            ],
        ];

        foreach ($groups as $data) {
            $group = Group::factory()->create([
                'name'         => $data['name'],
                'type'         => $data['type'],
                'community_id' => $data['community_id'] ?? (fake()->boolean(70) && $communityIds->isNotEmpty()
                    ? $communityIds->random()
                    : null),
                'slug'        => Str::slug($data['name']),
                'description' => fake()->sentence(12),
            ]);

            foreach ($memberships[$data['name']] ?? [] as $email => $isCoordinator) {
                $user = User::query()->where('email', $email)->firstOrFail();
                $group->users()->attach($user, ['is_coordinator' => $isCoordinator]);
            }
        }
    }
}
