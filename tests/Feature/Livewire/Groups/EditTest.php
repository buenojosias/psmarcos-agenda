<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use Livewire\Livewire;
use App\Enums\GroupTypeEnum;
use App\Livewire\Groups\Edit;
use App\Livewire\Groups\Show;

it('uses the same permissions as membership management', function (string $role, bool $active, bool $coordinator, bool $allowed) {
    $user  = User::factory()->create(['roles' => [$role], 'is_active' => $active]);
    $group = Group::create(['name' => 'Grupo Original', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP]);
    $group->users()->attach($user, ['is_coordinator' => $coordinator]);

    expect($user->can('update', $group))->toBe($allowed);
})->with([
    'admin'                => ['admin', true, false, true],
    'cpp'                  => ['cpp', true, false, true],
    'secretary'            => ['secretary', true, false, true],
    'coordinator'          => ['member', true, true, true],
    'member'               => ['member', true, false, false],
    'pascom'               => ['pascom', true, false, false],
    'priest'               => ['priest', true, false, false],
    'inactive admin'       => ['admin', false, false, false],
    'inactive coordinator' => ['member', false, true, false],
]);

it('loads and updates group details and closes the modal', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::create(['name' => 'Grupo Original', 'abbreviation' => 'GO', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP]);

    Livewire::actingAs($user)->test(Edit::class, ['group' => $group])
        ->call('open')->assertSet('name', 'Grupo Original')->assertSet('abbreviation', 'GO')->assertSet('modal', true)
        ->set('name', 'Pastoral Renovada')->set('abbreviation', 'PR')->set('type', GroupTypeEnum::PASTORAL->value)
        ->set('description', 'Descrição atualizada')->call('save')
        ->assertHasNoErrors()->assertSet('modal', false)->assertDispatched('group-updated');

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Pastoral Renovada', 'abbreviation' => 'PR', 'slug' => 'pastoral-renovada', 'type' => GroupTypeEnum::PASTORAL->value, 'description' => 'Descrição atualizada']);
});

it('allows saving an unchanged name and clearing optional details', function () {
    $user  = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group = Group::create(['name' => 'Grupo Original', 'abbreviation' => 'GO', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP, 'description' => 'Descrição']);
    $group->users()->attach($user, ['is_coordinator' => true]);

    Livewire::actingAs($user)->test(Edit::class, ['group' => $group])
        ->call('open')->set('abbreviation', null)->set('description', null)->set('communityId', null)->call('save')->assertHasNoErrors();

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Grupo Original', 'abbreviation' => null, 'description' => null, 'community_id' => null]);
});

it('hides editing and forbids crafted actions for a coordinator of another group', function () {
    $user  = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group = Group::create(['name' => 'Grupo Original', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP]);
    $other = Group::create(['name' => 'Outro Grupo', 'slug' => 'outro-grupo', 'type' => GroupTypeEnum::GROUP]);
    $other->users()->attach($user, ['is_coordinator' => true]);

    Livewire::actingAs($user)->test(Show::class, ['group' => $group])->assertDontSee('Editar grupo');
    Livewire::test(Edit::class, ['group' => $group])->call('open')->assertForbidden();
    Livewire::test(Edit::class, ['group' => $group])->set('name', 'Invadido')->call('save')->assertForbidden();

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Grupo Original']);
});

it('rechecks permission when saving after coordinator removal', function () {
    $user  = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group = Group::create(['name' => 'Grupo Original', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP]);
    $group->users()->attach($user, ['is_coordinator' => true]);
    $component = Livewire::actingAs($user)->test(Edit::class, ['group' => $group])->call('open');
    $group->users()->detach($user);

    $component->set('name', 'Invadido')->call('save')->assertForbidden();

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Grupo Original']);
});

it('validates edited details without changing the group', function (string $field, mixed $value, string $rule) {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::create(['name' => 'Grupo Original', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP]);

    Livewire::actingAs($user)->test(Edit::class, ['group' => $group])
        ->call('open')->set($field, $value)->call('save')->assertHasErrors([$field => $rule])->assertNotDispatched('group-updated');

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Grupo Original', 'type' => GroupTypeEnum::GROUP->value, 'community_id' => null]);
})->with([
    'required name'     => ['name', '', 'required'],
    'long name'         => ['name', str_repeat('a', 256), 'max'],
    'long abbreviation' => ['abbreviation', str_repeat('A', 31), 'max'],
    'required type'     => ['type', '', 'required'],
    'invalid type'      => ['type', 'invalid', 'Illuminate\Validation\Rules\Enum'],
    'missing community' => ['communityId', 999999, 'exists'],
]);

it('refuses duplicate slugs including deleted groups and invalid names', function (string $name, bool $deleted) {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::create(['name' => 'Grupo Original', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP]);
    $other = Group::create(['name' => 'São José', 'slug' => 'sao-jose', 'type' => GroupTypeEnum::GROUP]);

    if ($deleted) {
        $other->delete();
    }

    Livewire::actingAs($user)->test(Edit::class, ['group' => $group])
        ->call('open')->set('name', $name)->call('save')->assertHasErrors(['name'])
        ->assertSee('Já existe um grupo com esse nome ou o nome é inválido.');

    $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Grupo Original']);
})->with([['Sao Jose', false], ['Sao Jose', true], ['!!!', false]]);

it('shows editing to authorized users and refreshes displayed details', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group     = Group::create(['name' => 'Grupo Original', 'slug' => 'grupo-original', 'type' => GroupTypeEnum::GROUP]);
    $component = Livewire::actingAs($user)->test(Show::class, ['group' => $group])->assertSee('Editar grupo');
    $group->update(['name' => 'Grupo Atualizado', 'description' => 'Nova descrição']);

    $component->call('refreshGroup')->assertSee(['Grupo Atualizado', 'Nova descrição']);
});
