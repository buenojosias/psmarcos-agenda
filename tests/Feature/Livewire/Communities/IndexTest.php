<?php

declare(strict_types=1);

use App\Models\User;
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
    $canManage ? $response->assertSee('Cadastrar comunidade')
        : $response->assertDontSee('Cadastrar comunidade');
    $response->assertDontSee('Editar comunidade')->assertDontSee('Excluir');
})->with(UserRoleEnum::cases());

it('requires authentication and activity to list communities', function () {
    $this->get(route('communities.index'))->assertRedirect(route('login'));
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)->get(route('communities.index'))->assertRedirect(route('registration.pending'));
    Livewire::actingAs($user)->test(Index::class)->assertForbidden();
});

it('shows a friendly empty state', function () {
    $user = User::factory()->create(['roles' => [UserRoleEnum::MEMBER->value], 'is_active' => true]);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('Ainda não há comunidades cadastradas.')->assertDontSee('Cadastrar comunidade');
});

it('lists communities by ascending id regardless of their names', function () {
    $user = User::factory()->create(['is_active' => true]);
    Community::create(['name' => 'Zeladora', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    Community::create(['name' => 'Alvorada', 'alias' => 'Capela', 'abbreviation' => 'CP']);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSeeInOrder(['Zeladora', 'Alvorada']);
});

it('shows the neighborhood and leaves a missing neighborhood blank', function () {
    $user = User::factory()->create(['is_active' => true]);
    Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT', 'address' => 'Rua das Flores', 'neighborhood' => 'Centro']);
    Community::create(['name' => 'Capela', 'alias' => 'Capela', 'abbreviation' => 'CP', 'neighborhood' => null]);

    Livewire::actingAs($user)->test(Index::class)
        ->assertSee('Centro')
        ->assertDontSee('Rua das Flores')
        ->assertDontSee('—');
});
