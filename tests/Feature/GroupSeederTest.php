<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use App\Models\Community;
use Database\Seeders\GroupSeeder;
use Database\Seeders\CommunitySeeder;

it('assigns the requested communities and group memberships', function () {
    $this->seed(CommunitySeeder::class);

    foreach (['josias@email.com', 'pascom@email.com', 'cpp@email.com'] as $email) {
        User::factory()->create(['email' => $email]);
    }

    $this->seed(GroupSeeder::class);

    $matrizId = Community::query()->where('name', 'Matriz São Marcos')->firstOrFail()->id;
    $beatoId  = Community::query()->where('name', 'Capela Beato Giacomo Cusmano')->firstOrFail()->id;

    foreach (['Pastoral da Liturgia', 'Pastoral da Comunicação', 'Conselho Pastoral Paroquial', 'Coral Doce Canto'] as $name) {
        $this->assertDatabaseHas('groups', ['name' => $name, 'community_id' => $matrizId]);
    }

    $this->assertDatabaseHas('groups', [
        'name'         => 'Grupo de Jovens do Beato',
        'community_id' => $beatoId,
    ]);

    foreach ([
        ['Pastoral da Comunicação', 'josias@email.com', false],
        ['Pastoral da Comunicação', 'pascom@email.com', false],
        ['Conselho Pastoral Paroquial', 'cpp@email.com', false],
        ['Coral Doce Canto', 'josias@email.com', true],
    ] as [$groupName, $email, $isCoordinator]) {
        $this->assertDatabaseHas('group_user', [
            'group_id'       => Group::query()->where('name', $groupName)->firstOrFail()->id,
            'user_id'        => User::query()->where('email', $email)->firstOrFail()->id,
            'is_coordinator' => $isCoordinator,
        ]);
    }
});
