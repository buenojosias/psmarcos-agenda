<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Communities\Show;

it('shows community details and only its own groups', function () {
    $this->withoutVite();
    $user      = User::factory()->create(['roles' => [UserRoleEnum::MEMBER->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Comunidade São Marcos', 'alias' => 'Matriz', 'abbreviation' => 'MT', 'address' => 'Praça central']);
    $other     = Community::create(['name' => 'Outra', 'alias' => 'Capela', 'abbreviation' => 'CP']);
    $group     = Group::factory()->create(['community_id' => $community->id, 'name' => 'Grupo da Matriz']);
    Group::factory()->create(['community_id' => $other->id, 'name' => 'Grupo da Capela']);
    Group::factory()->create(['community_id' => null, 'name' => 'Grupo Paroquial']);

    $this->actingAs($user)->get(route('communities.show', $community))
        ->assertOk()->assertSee('Comunidade São Marcos')->assertSee('Matriz')
        ->assertSee('MT')->assertSee('Praça central')->assertSee('Grupo da Matriz')
        ->assertSee(route('groups.show', $group))->assertDontSee('Grupo da Capela')
        ->assertDontSee('Grupo Paroquial')->assertDontSee('Editar comunidade');
});

it('shows an empty groups message and refreshes edited community details', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $component = Livewire::actingAs($user)->test(Show::class, ['community' => $community])
        ->assertSee('Nenhum grupo está vinculado a esta comunidade.')->assertSee('Editar comunidade');
    $community->update(['name' => 'Nome atualizado']);

    $component->call('refreshCommunity')->assertSee('Nome atualizado');
});

it('requires authentication and activity to view a community', function () {
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $this->get(route('communities.show', $community))->assertRedirect(route('login'));
    $user = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => false]);

    $this->actingAs($user)->get(route('communities.show', $community))->assertForbidden();
    Livewire::actingAs($user)->test(Show::class, ['community' => $community])->assertForbidden();
});
