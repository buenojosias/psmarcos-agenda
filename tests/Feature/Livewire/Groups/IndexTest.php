<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\GroupTypeEnum;
use App\Livewire\Groups\Index;

it('requires authentication to visit the groups page', function () {
    $this->get(route('groups.index'))->assertRedirect(route('login'));
});

it('lists groups with their type and community', function () {
    $community = Community::create(['name' => 'Comunidade São José', 'abbreviation' => 'CSJ', 'alias' => 'sao-jose']);
    Group::create([
        'community_id' => $community->id,
        'name'         => 'Pastoral da Saúde',
        'slug'         => 'pastoral-da-saude',
        'type'         => GroupTypeEnum::PASTORAL,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('groups.index'))
        ->assertOk()
        ->assertSee('Pastoral da Saúde')
        ->assertSee('Pastoral')
        ->assertSee('Comunidade São José');
});

it('filters groups by name and excludes deleted groups', function () {
    $matching = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP]);
    Group::create(['name' => 'Grupo Alegria', 'slug' => 'grupo-alegria', 'type' => GroupTypeEnum::GROUP]);
    Group::create(['name' => 'Grupo Esperança Antigo', 'slug' => 'grupo-esperanca-antigo', 'type' => GroupTypeEnum::GROUP])->delete();

    $rows = Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->set('search', 'Esperança')
        ->get('rows');

    expect($rows->total())->toBe(1)
        ->and($rows->first()->id)->toBe($matching->id);
});
