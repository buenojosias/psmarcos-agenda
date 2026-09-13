<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Livewire\Users\Create;
use App\Livewire\Users\Delete;
use App\Livewire\Users\Update;
use Illuminate\Support\Facades\Gate;

it('allows active administrators, secretaries and CPP coordinators to manage users', function (array $roles, bool $active, bool $allowed) {
    $actor  = User::factory()->create(['roles' => $roles, 'is_active' => $active]);
    $target = User::factory()->create();

    expect(Gate::forUser($actor)->allows('viewAny', User::class))->toBe($allowed)
        ->and(Gate::forUser($actor)->allows('create', User::class))->toBe($allowed)
        ->and(Gate::forUser($actor)->allows('update', $target))->toBe($allowed)
        ->and(Gate::forUser($actor)->allows('delete', $target))->toBe($allowed);
})->with([
    'administrator'          => [['admin'], true, true],
    'inactive administrator' => [['admin'], false, false],
    'inactive secretary'     => [['secretary'], false, false],
    'member'                 => [['member'], true, false],
    'secretary'              => [['secretary'], true, true],
    'pascom'                 => [['pascom'], true, false],
    'cpp'                    => [['cpp'], true, true],
    'priest'                 => [['priest'], true, false],
    'no role'                => [[], true, false],
]);

it('prevents an administrator from editing or deleting their own account through user management', function () {
    $actor = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    expect(Gate::forUser($actor)->denies('update', $actor))->toBeTrue()
        ->and(Gate::forUser($actor)->denies('delete', $actor))->toBeTrue();
});

it('prevents secretaries and CPP coordinators from changing or deleting administrators', function (string $role) {
    $actor = User::factory()->create(['roles' => [$role], 'is_active' => true]);
    $admin = User::factory()->create(['roles' => ['admin', 'member']]);

    expect(Gate::forUser($actor)->denies('update', $admin))->toBeTrue()
        ->and(Gate::forUser($actor)->denies('delete', $admin))->toBeTrue();

    Livewire::actingAs($actor)->test(Update::class, ['user' => $admin])->call('save')->assertForbidden();
    Livewire::actingAs($actor)->test(Delete::class, ['user' => $admin])->call('delete')->assertForbidden();

    $this->assertModelExists($admin);
})->with(['secretary', 'cpp']);

it('hides administrators from the list shown to secretaries and CPP coordinators', function (string $role) {
    $actor  = User::factory()->create(['roles' => [$role], 'is_active' => true]);
    $admin  = User::factory()->create(['roles' => ['admin']]);
    $member = User::factory()->create(['roles' => ['member']]);

    $rows = Livewire::actingAs($actor)->test(App\Livewire\Users\Index::class)->get('rows');

    expect($rows->pluck('id'))->toContain($member->id)->not->toContain($admin->id);
})->with(['secretary', 'cpp']);

it('protects the user management route', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));

    $member = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $this->actingAs($member)->get(route('users.index'))->assertForbidden();

    $admin = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $this->actingAs($admin)->get(route('users.index'))->assertOk();

    foreach (['secretary', 'cpp'] as $role) {
        $manager = User::factory()->create(['roles' => [$role], 'is_active' => true]);
        $this->actingAs($manager)->get(route('users.index'))->assertOk();
    }
});

it('refuses direct user management actions from a member', function () {
    $member = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $target = User::factory()->create();

    Livewire::actingAs($member)->test(Create::class)->call('save')->assertForbidden();
    Livewire::actingAs($member)->test(Update::class, ['user' => $target])->call('save')->assertForbidden();
    Livewire::actingAs($member)->test(Delete::class, ['user' => $target])->call('delete')->assertForbidden();

    $this->assertModelExists($target);
});
