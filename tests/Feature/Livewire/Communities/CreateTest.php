<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Livewire\Communities\Create;

it('allows active administrators and CPP to create communities', function (UserRoleEnum $role) {
    $user = User::factory()->create(['roles' => [$role->value], 'is_active' => true]);

    Livewire::actingAs($user)->test(Create::class)
        ->set('name', 'Comunidade São Marcos')
        ->set('alias', 'São Marcos')
        ->set('abbreviation', ' sm ')
        ->set('address', 'Rua da Matriz, 10')
        ->call('save')->assertHasNoErrors()
        ->assertSet('modal', false)->assertDispatched('created');

    $this->assertDatabaseHas('communities', [
        'name'         => 'Comunidade São Marcos', 'alias' => 'São Marcos',
        'abbreviation' => 'SM', 'address' => 'Rua da Matriz, 10',
    ]);
})->with([UserRoleEnum::ADMIN, UserRoleEnum::CPP]);

it('forbids creation by other roles and inactive managers', function (UserRoleEnum $role, bool $active) {
    $user = User::factory()->create(['roles' => [$role->value], 'is_active' => $active]);

    Livewire::actingAs($user)->test(Create::class)
        ->set('name', 'Comunidade proibida')->set('alias', 'Proibida')
        ->set('abbreviation', 'CP')->call('save')->assertForbidden();

    $this->assertDatabaseCount('communities', 0);
})->with([
    [UserRoleEnum::MEMBER, true], [UserRoleEnum::SECRETARY, true],
    [UserRoleEnum::PASCOM, true], [UserRoleEnum::PRIEST, true],
    [UserRoleEnum::ADMIN, false], [UserRoleEnum::CPP, false],
]);

it('requires name alias and abbreviation', function () {
    $user = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);

    Livewire::actingAs($user)->test(Create::class)->call('save')
        ->assertHasErrors(['name' => 'required', 'alias' => 'required', 'abbreviation' => 'required'])
        ->assertNotDispatched('created');

    $this->assertDatabaseCount('communities', 0);
});

it('rejects duplicate and oversized fields', function (string $field, string $value, string $rule) {
    $user = User::factory()->create(['roles' => [UserRoleEnum::ADMIN->value], 'is_active' => true]);
    Community::create(['name' => 'Existente', 'alias' => 'Existente', 'abbreviation' => 'EX']);

    Livewire::actingAs($user)->test(Create::class)
        ->set('name', 'Nova comunidade')->set('alias', 'Nova')->set('abbreviation', 'NV')
        ->set($field, $value)->call('save')->assertHasErrors([$field => $rule])
        ->assertNotDispatched('created');

    $this->assertDatabaseCount('communities', 1);
})->with([
    'duplicate alias'                   => ['alias', 'Existente', 'unique'],
    'normalized duplicate abbreviation' => ['abbreviation', 'ex', 'unique'],
    'long abbreviation'                 => ['abbreviation', 'ABCDE', 'max'],
    'long alias'                        => ['alias', str_repeat('a', 31), 'max'],
    'long name'                         => ['name', str_repeat('a', 121), 'max'],
    'long address'                      => ['address', str_repeat('a', 256), 'max'],
]);
