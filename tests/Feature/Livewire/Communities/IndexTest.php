<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Communities\Index;

it('allows every active role to list communities and exposes only authorized actions', function (UserRoleEnum $role) {
    $this->withoutVite();
    $user      = User::factory()->create(['roles' => [$role->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Comunidade São Marcos', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    $response = $this->actingAs($user)->get(route('communities.index'))
        ->assertOk()->assertSee('Comunidade São Marcos')->assertSee(route('communities.show', $community));

    $canManage = in_array($role, [UserRoleEnum::ADMIN, UserRoleEnum::CPP], true);
    expect($user->can('create', Community::class))->toBe($canManage);
    expect($user->can('update', $community))->toBe($canManage);
    expect($user->can('delete', $community))->toBe($canManage);
    $canManage ? $response->assertSee('Cadastrar comunidade')->assertSee('Editar comunidade')
        : $response->assertDontSee('Cadastrar comunidade')->assertDontSee('Editar comunidade');
})->with(UserRoleEnum::cases());

it('requires authentication and activity to list communities', function () {
    $this->get(route('communities.index'))->assertRedirect(route('login'));
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)->get(route('communities.index'))->assertForbidden();
    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
});

it('shows a friendly empty state', function () {
    $user = User::factory()->create(['roles' => [UserRoleEnum::MEMBER->value], 'is_active' => true]);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('Ainda não há comunidades cadastradas.')->assertDontSee('Cadastrar comunidade');
});

it('lists communities and safely handles invalid sorting input', function () {
    $user = User::factory()->create(['is_active' => true]);
    Community::create(['name' => 'Comunidade São Marcos', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    Community::create(['name' => 'Outra', 'alias' => 'Capela', 'abbreviation' => 'CP']);

    Livewire::actingAs($user)->test(Index::class)
        ->set('sort', ['column' => 'invalid', 'direction' => 'invalid'])
        ->assertSee('Comunidade São Marcos')->assertSee('Outra');
});

it('asks for confirmation before deleting an empty community', function (UserRoleEnum $role) {
    $user      = User::factory()->create(['roles' => [$role->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    $component = Livewire::actingAs($user)->test(Index::class)->call('delete', $community->id)
        ->assertDispatched('ts-ui:dialog');
    $this->assertModelExists($community);

    $component->call('confirmDelete', $community->id)->assertDispatched('ts-ui:dialog');
    $this->assertModelMissing($community);
})->with([UserRoleEnum::ADMIN, UserRoleEnum::CPP]);

it('forbids crafted deletion actions from nonmanagers', function (UserRoleEnum $role) {
    $user      = User::factory()->create(['roles' => [$role->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    Livewire::actingAs($user)->test(Index::class)->call('delete', $community->id)->assertForbidden();
    Livewire::test(Index::class)->call('confirmDelete', $community->id)->assertForbidden();

    $this->assertModelExists($community);
})->with([UserRoleEnum::MEMBER, UserRoleEnum::SECRETARY, UserRoleEnum::PASCOM, UserRoleEnum::PRIEST]);

it('preserves communities and dependent records when deletion is requested', function (string $relation) {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    if ($relation === 'users') {
        $community->users()->attach($user);
    } elseif ($relation === 'places') {
        $community->places()->create(['name' => 'Salão']);
    } else {
        $group = Group::factory()->create(['community_id' => $community->id]);

        if ($relation === 'archived groups') {
            $group->delete();
        }
    }

    Livewire::actingAs($user)->test(Index::class)->call('confirmDelete', $community->id)
        ->assertDispatched('ts-ui:dialog', fn (string $event, array $params): bool => str_contains(json_encode($params), 'registros vinculados'));

    $this->assertModelExists($community);

    if ($relation === 'users') {
        $this->assertDatabaseHas('community_user', ['community_id' => $community->id, 'user_id' => $user->id]);
    } elseif ($relation === 'places') {
        $this->assertDatabaseHas('places', ['community_id' => $community->id, 'name' => 'Salão']);
    } else {
        $this->assertDatabaseHas('groups', ['id' => $group->id, 'community_id' => $community->id]);
    }
})->with(['users', 'places', 'groups', 'archived groups']);

it('rechecks active status when confirming deletion', function (UserRoleEnum $role) {
    $user      = User::factory()->create(['roles' => [$role->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $component = Livewire::actingAs($user)->test(Index::class)->call('delete', $community->id);
    $user->update(['is_active' => false]);

    $component->call('confirmDelete', $community->id)->assertForbidden();

    $this->assertModelExists($community);
})->with([UserRoleEnum::ADMIN, UserRoleEnum::CPP]);
