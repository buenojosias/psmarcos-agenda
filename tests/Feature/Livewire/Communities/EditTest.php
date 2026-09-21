<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Communities\Edit;

it('allows administrators and CPP to edit while retaining unique values', function (UserRoleEnum $role) {
    $user      = User::factory()->create(['roles' => [$role->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT', 'address' => 'Rua antiga']);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open')->assertSet('name', 'Original')->assertSet('alias', 'Matriz')->assertSet('modal', true)
        ->set('name', 'Nome atualizado')->set('address', null)
        ->call('save')->assertHasNoErrors()->assertSet('modal', false)->assertDispatched('community-updated');

    $this->assertDatabaseHas('communities', [
        'id'           => $community->id, 'name' => 'Nome atualizado', 'alias' => 'Matriz',
        'abbreviation' => 'MT', 'address' => null,
    ]);
})->with([UserRoleEnum::ADMIN, UserRoleEnum::CPP]);

it('forbids opening and saving edits for other roles even when linked to the community', function (UserRoleEnum $role, bool $active) {
    $user      = User::factory()->create(['roles' => [$role->value], 'is_active' => $active]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $community->users()->attach($user);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])->call('open')->assertForbidden();
    Livewire::test(Edit::class, ['community' => $community])
        ->set('name', 'Invadida')->call('save')->assertForbidden();

    $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => 'Original']);
})->with([
    [UserRoleEnum::MEMBER, true], [UserRoleEnum::SECRETARY, true],
    [UserRoleEnum::PASCOM, true], [UserRoleEnum::PRIEST, true],
    [UserRoleEnum::ADMIN, false], [UserRoleEnum::CPP, false],
]);

it('refuses invalid edits and values owned by another community', function (string $field, string $value, string $rule) {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    Community::create(['name' => 'Outra', 'alias' => 'Capela', 'abbreviation' => 'CP']);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open')->set($field, $value)->call('save')
        ->assertHasErrors([$field => $rule])->assertNotDispatched('community-updated');

    $this->assertDatabaseHas('communities', [
        'id' => $community->id, 'name' => 'Original', 'alias' => 'Matriz', 'abbreviation' => 'MT',
    ]);
})->with([
    ['name', '', 'required'], ['alias', '', 'required'], ['abbreviation', '', 'required'],
    ['alias', 'Capela', 'unique'], ['abbreviation', 'cp', 'unique'],
    ['name', str_repeat('a', 121), 'max'], ['alias', str_repeat('a', 31), 'max'],
    ['abbreviation', 'ABCDE', 'max'], ['address', str_repeat('a', 256), 'max'],
]);
