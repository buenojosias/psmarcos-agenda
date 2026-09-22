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

    $group = Group::where('slug', 'pastoral-da-saude')->firstOrFail();

    $this->actingAs(User::factory()->create())
        ->get(route('groups.index'))
        ->assertOk()
        ->assertSee('Pastoral da Saúde')
        ->assertSee(route('groups.show', $group))
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

it('filters groups by community', function () {
    $community      = Community::create(['name' => 'São José', 'abbreviation' => 'SJ', 'alias' => 'sao-jose']);
    $otherCommunity = Community::create(['name' => 'São Pedro', 'abbreviation' => 'SP', 'alias' => 'sao-pedro']);
    $matching       = Group::create(['name' => 'Pastoral da Saúde', 'slug' => 'pastoral-saude', 'type' => GroupTypeEnum::PASTORAL, 'community_id' => $community->id]);
    Group::create(['name' => 'Pastoral da Família', 'slug' => 'pastoral-familia', 'type' => GroupTypeEnum::PASTORAL, 'community_id' => $otherCommunity->id]);
    Group::create(['name' => 'Grupo sem comunidade', 'slug' => 'sem-comunidade', 'type' => GroupTypeEnum::GROUP]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->assertSee('Todas as comunidades')
        ->set('community', (string) $community->id);

    expect($component->get('rows')->pluck('id')->all())->toBe([$matching->id]);
});

it('filters groups without a community and restores all groups', function () {
    $community  = Community::create(['name' => 'São José', 'abbreviation' => 'SJ', 'alias' => 'sao-jose']);
    $unassigned = Group::create(['name' => 'Grupo de Jovens', 'slug' => 'jovens', 'type' => GroupTypeEnum::GROUP]);
    Group::create(['name' => 'Grupo de Liturgia', 'slug' => 'liturgia', 'type' => GroupTypeEnum::GROUP, 'community_id' => $community->id]);

    $component = Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->assertSee('Sem comunidade')
        ->set('community', 'none');

    expect($component->get('rows')->pluck('id')->all())->toBe([$unassigned->id]);

    $component->set('community', '');

    expect($component->get('rows')->total())->toBe(2);
});

it('shows only groups joined by the user when requested', function () {
    $user   = User::factory()->create();
    $joined = Group::create(['name' => 'Grupo de Jovens', 'slug' => 'jovens', 'type' => GroupTypeEnum::GROUP]);
    Group::create(['name' => 'Grupo de Liturgia', 'slug' => 'liturgia', 'type' => GroupTypeEnum::GROUP]);
    $user->groups()->attach($joined);

    $component = Livewire::actingAs($user)
        ->test(Index::class)
        ->assertSee('Apenas grupos dos quais participo')
        ->set('myGroups', true);

    expect($component->get('rows')->pluck('id')->all())->toBe([$joined->id]);
});

it('combines filters and returns to the first page when one changes', function () {
    $user      = User::factory()->create();
    $community = Community::create(['name' => 'São José', 'abbreviation' => 'SJ', 'alias' => 'sao-jose']);
    $matching  = Group::create(['name' => 'Grupo Esperança', 'slug' => 'esperanca', 'type' => GroupTypeEnum::GROUP, 'community_id' => $community->id]);
    $user->groups()->attach($matching);
    Group::create(['name' => 'Grupo Alegria', 'slug' => 'alegria', 'type' => GroupTypeEnum::GROUP, 'community_id' => $community->id]);
    Group::create(['name' => 'Grupo Esperança Externo', 'slug' => 'esperanca-externo', 'type' => GroupTypeEnum::GROUP]);

    $component = Livewire::actingAs($user)
        ->test(Index::class)
        ->call('setPage', 2)
        ->set('community', (string) $community->id)
        ->set('search', 'Esperança')
        ->set('myGroups', true)
        ->assertSet('paginators.page', 1);

    expect($component->get('rows')->pluck('id')->all())->toBe([$matching->id]);
});
