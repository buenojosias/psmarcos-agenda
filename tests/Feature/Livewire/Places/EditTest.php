<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Places\Edit;

it('updates spaces and subspaces without changing their relationships', function (bool $subspace) {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::SECRETARY->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $parent    = $subspace ? $community->places()->create(['name' => 'Salão']) : null;
    $place     = $community->places()->create(['name' => 'Original', 'main_place_id' => $parent?->id]);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->dispatch('edit-place', placeId: $place->id)
        ->assertSet('name', 'Original')->assertSet('modal', true)
        ->set('name', ' Novo nome ')->call('save')->assertHasNoErrors()
        ->assertSet('modal', false)->assertDispatched('place-updated');

    $this->assertDatabaseHas('places', [
        'id'            => $place->id,
        'name'          => 'Novo nome',
        'community_id'  => $community->id,
        'main_place_id' => $parent?->id,
    ]);
})->with([false, true]);

it('forbids unauthorized users from opening the editor', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::MEMBER->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Original']);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open', $place->id)->assertForbidden();
});

it('rechecks permissions when saving', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Original']);
    $component = Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open', $place->id)->set('name', 'Proibido');
    $user->update(['roles' => [UserRoleEnum::MEMBER->value]]);

    $component->call('save')->assertForbidden();

    $this->assertDatabaseHas('places', ['id' => $place->id, 'name' => 'Original']);
});

it('rejects a space belonging to another community', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $other     = Community::create(['name' => 'Capela', 'alias' => 'Capela', 'abbreviation' => 'CP']);
    $place     = $other->places()->create(['name' => 'Original']);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open', $place->id)->assertNotFound();

    $this->assertDatabaseHas('places', ['id' => $place->id, 'name' => 'Original']);
});

it('rejects saving a space that was deleted after opening', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Original']);
    $component = Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open', $place->id)->set('name', 'Novo');
    $place->delete();

    $component->call('save')->assertNotFound();

    $this->assertDatabaseCount('places', 0);
});

it('validates the name before updating', function (string $name, string $rule) {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Original']);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open', $place->id)->set('name', $name)->call('save')
        ->assertHasErrors(['name' => $rule])->assertNotDispatched('place-updated');

    $this->assertDatabaseHas('places', ['id' => $place->id, 'name' => 'Original']);
})->with([
    'empty'      => ['', 'required'],
    'whitespace' => ['   ', 'required'],
    'too long'   => [str_repeat('a', 256), 'max'],
]);

it('loads the selected space and clears previous validation errors when reopened', function () {
    $user      = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);
    $other     = $community->places()->create(['name' => 'Cozinha']);

    Livewire::actingAs($user)->test(Edit::class, ['community' => $community])
        ->call('open', $place->id)->set('name', '')->call('save')->assertHasErrors('name')
        ->call('open', $other->id)->assertHasNoErrors()
        ->assertSet('placeId', $other->id)->assertSet('name', 'Cozinha')->assertSet('modal', true);
});
