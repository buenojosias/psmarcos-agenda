<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Places\Create;

it('creates root spaces and subspaces in the current community', function (bool $subspace) {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::SECRETARY->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $parent    = $subspace ? $community->places()->create(['name' => 'Salão']) : null;

    Livewire::actingAs($user)->test(Create::class, ['community' => $community])
        ->dispatch('create-place', mainPlaceId: $parent?->id)
        ->assertSet('modal', true)
        ->set('name', ' Cozinha ')
        ->call('save')->assertHasNoErrors()
        ->assertSet('modal', false)->assertDispatched('created');

    $this->assertDatabaseHas('places', [
        'community_id'  => $community->id,
        'main_place_id' => $parent?->id,
        'name'          => 'Cozinha',
    ]);
})->with([false, true]);

it('forbids opening and saving for unauthorized users', function (string $action) {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::MEMBER->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    Livewire::actingAs($user)->test(Create::class, ['community' => $community])
        ->set('name', 'Proibido')->call($action)->assertForbidden();

    $this->assertDatabaseCount('places', 0);
})->with(['open', 'save']);

it('rejects parents from another community', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $other     = Community::create(['name' => 'Capela', 'alias' => 'Capela', 'abbreviation' => 'CP']);
    $parent    = $other->places()->create(['name' => 'Salão']);

    Livewire::actingAs($user)->test(Create::class, ['community' => $community])
        ->call('open', $parent->id)->assertNotFound();

    $this->assertDatabaseCount('places', 1);
});

it('does not save a subspace if its parent was deleted', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $parent    = $community->places()->create(['name' => 'Salão']);
    $component = Livewire::actingAs($user)->test(Create::class, ['community' => $community])
        ->call('open', $parent->id)->set('name', 'Cozinha');
    $parent->delete();

    $component->call('save')->assertNotFound();

    $this->assertDatabaseCount('places', 0);
});

it('validates the space name', function (string $name, string $rule) {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    Livewire::actingAs($user)->test(Create::class, ['community' => $community])
        ->set('name', $name)->call('save')
        ->assertHasErrors(['name' => $rule])->assertNotDispatched('created');

    $this->assertDatabaseCount('places', 0);
})->with([
    'empty'      => ['', 'required'],
    'whitespace' => ['   ', 'required'],
    'too long'   => [str_repeat('a', 256), 'max'],
]);

it('clears the parent name and validation errors when reopening for a root space', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $parent    = $community->places()->create(['name' => 'Salão']);

    Livewire::actingAs($user)->test(Create::class, ['community' => $community])
        ->call('open', $parent->id)->call('save')->assertHasErrors('name')
        ->set('name', 'Rascunho')->call('open')
        ->assertSet('mainPlaceId', null)->assertSet('name', '')
        ->assertHasNoErrors()->assertSet('modal', true);
});
