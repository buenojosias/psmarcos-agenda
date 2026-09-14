<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Group;
use Livewire\Livewire;
use App\Enums\GroupTypeEnum;
use App\Livewire\Groups\Create;

it('shows user search to managers and lets them create a group without a user', function (string $role) {
    $manager = User::factory()->create(['roles' => [$role], 'is_active' => true]);

    Livewire::actingAs($manager)->test(Create::class)
        ->assertSee('Usuário')
        ->assertSee('É coordenador deste grupo')
        ->assertDontSee('Sou coordenador deste grupo')
        ->set('name', 'Pastoral da Saúde')
        ->set('type', GroupTypeEnum::PASTORAL->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('created');

    $group = Group::where('slug', 'pastoral-da-saude')->firstOrFail();
    expect($group->users()->count())->toBe(0);
})->with(['admin', 'cpp', 'pascom', 'secretary']);

it('lets a manager select a user and mark that user as coordinator', function () {
    $manager  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $selected = User::factory()->create(['name' => 'Maria Silva']);

    Livewire::actingAs($manager)->test(Create::class)
        ->set('name', 'Grupo de Jovens')
        ->set('type', GroupTypeEnum::GROUP->value)
        ->set('userId', $selected->id)
        ->set('isLeader', true)
        ->call('save')
        ->assertHasNoErrors();

    $group = Group::where('slug', 'grupo-de-jovens')->firstOrFail();
    expect($group->users()->whereKey($selected->id)->first()->pivot->is_coordinator)->toBe(1);
});

it('lets a member create a group linked to themselves with the toggle value', function (bool $isLeader) {
    $member = User::factory()->create(['roles' => ['member'], 'is_active' => true]);

    Livewire::actingAs($member)->test(Create::class)
        ->assertSee('Sou coordenador deste grupo')
        ->assertDontSee('É coordenador deste grupo')
        ->set('name', 'Grupo Esperança')
        ->set('type', GroupTypeEnum::GROUP->value)
        ->set('isLeader', $isLeader)
        ->call('save')
        ->assertHasNoErrors();

    $group = Group::where('slug', 'grupo-esperanca')->firstOrFail();
    expect($group->users()->whereKey($member->id)->first()->pivot->is_coordinator)->toBe((int) $isLeader);
})->with([false, true]);

it('prevents members from assigning another user through a crafted Livewire request', function () {
    $member = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $other  = User::factory()->create();

    Livewire::actingAs($member)->test(Create::class)
        ->set('name', 'Grupo Invasor')
        ->set('type', GroupTypeEnum::GROUP->value)
        ->set('userId', $other->id)
        ->call('save')
        ->assertForbidden();

    expect(Group::where('slug', 'grupo-invasor')->exists())->toBeFalse();
});

it('rejects a missing group name and type', function () {
    $manager = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($manager)->test(Create::class)
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'type' => 'required']);

    expect(Group::count())->toBe(0);
});

it('rejects a nonexistent selected user', function () {
    $manager = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($manager)->test(Create::class)
        ->set('name', 'Grupo Novo')
        ->set('type', GroupTypeEnum::GROUP->value)
        ->set('userId', 999999)
        ->call('save')
        ->assertHasErrors(['userId' => 'exists']);

    expect(Group::count())->toBe(0);
});

it('rejects a group name that would duplicate an existing slug', function () {
    $manager = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Group::create(['name' => 'Grupo São José', 'slug' => 'grupo-sao-jose', 'type' => GroupTypeEnum::GROUP]);

    Livewire::actingAs($manager)->test(Create::class)
        ->set('name', 'Grupo Sao Jose')
        ->set('type', GroupTypeEnum::GROUP->value)
        ->call('save')
        ->assertHasErrors(['name']);

    expect(Group::count())->toBe(1);
});

it('forbids group creation by other roles and inactive users', function (array $roles, bool $isActive) {
    $user = User::factory()->create(['roles' => $roles, 'is_active' => $isActive]);

    Livewire::actingAs($user)->test(Create::class)
        ->set('name', 'Grupo Proibido')
        ->set('type', GroupTypeEnum::GROUP->value)
        ->call('save')
        ->assertForbidden();

    expect(Group::count())->toBe(0);
})->with([
    'priest'                  => [['priest'], true],
    'mixed member and priest' => [['member', 'priest'], true],
    'inactive member'         => [['member'], false],
    'inactive manager'        => [['admin'], false],
]);

it('only exposes user search to managers', function () {
    $manager  = User::factory()->create(['roles' => ['pascom'], 'is_active' => true]);
    $member   = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $selected = User::factory()->create(['name' => 'Maria Silva']);

    $this->actingAs($manager)->getJson(route('groups.users.search', ['search' => 'Maria']))
        ->assertOk()
        ->assertJson([['label' => 'Maria Silva', 'value' => $selected->id]]);

    $this->actingAs($member)->getJson(route('groups.users.search'))->assertForbidden();
});

it('requires authentication to search users for the group form', function () {
    $this->getJson(route('groups.users.search'))->assertUnauthorized();
});
