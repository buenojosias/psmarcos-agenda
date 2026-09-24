<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Enums\EventStatusEnum;
use Illuminate\Support\Facades\Gate;

it('keeps member visibility and policy aligned for every status and group ownership', function (EventStatusEnum $status, string $ownership) {
    $user  = User::factory()->create(['roles' => ['member']]);
    $group = $ownership === 'none' ? null : Group::factory()->create();

    if ($ownership === 'own') {
        $group->users()->attach($user);
    }
    $event   = Event::factory()->create(['group_id' => $group?->id, 'status' => $status]);
    $visible = $ownership === 'own' || $status === EventStatusEnum::CONFIRMED;

    expect(Event::query()->visibleTo($user)->whereKey($event)->exists())->toBe($visible);
    expect(Gate::forUser($user)->allows('view', $event))->toBe($visible);
})->with(EventStatusEnum::cases())->with(['own', 'other', 'none']);

it('allows elevated users to view every status with and without groups', function (array $roles) {
    $user  = User::factory()->create(['roles' => $roles]);
    $group = Group::factory()->create();

    foreach (EventStatusEnum::cases() as $status) {
        foreach ([$group->id, null] as $groupId) {
            $event = Event::factory()->create(['group_id' => $groupId, 'status' => $status]);
            expect(Gate::forUser($user)->allows('view', $event))->toBeTrue();
        }
    }

    expect(Event::query()->visibleTo($user)->count())->toBe(10);
})->with([
    'member and pascom' => [['member', 'pascom']],
    'pascom and member' => [['pascom', 'member']],
    'pascom'            => [['pascom']],
    'secretary'         => [['secretary']],
    'cpp'               => [['cpp']],
    'priest'            => [['priest']],
    'admin'             => [['admin']],
]);

it('identifies only the member role independent of keys and duplicates', function (array $roles, bool $expected) {
    expect(User::factory()->make(['roles' => $roles])->isMemberOnly())->toBe($expected);
})->with([
    [['member'], true],
    [['member', 'member'], true],
    [[3 => 'member'], true],
    [['pascom', 'member'], false],
    [[], false],
]);

it('allows only active users to list events', function () {
    expect(Gate::forUser(User::factory()->make(['is_active' => true]))->allows('viewAny', Event::class))->toBeTrue();
    expect(Gate::forUser(User::factory()->make(['is_active' => false]))->allows('viewAny', Event::class))->toBeFalse();
});

it('authorizes event updates according to active roles, creation, and group membership', function (array $roles, bool $isActive, bool $belongsToGroup, bool $isCreator, bool $expected) {
    $user  = User::factory()->create(['roles' => $roles, 'is_active' => $isActive]);
    $group = Group::factory()->create();

    if ($belongsToGroup) {
        $group->users()->attach($user);
    }

    $event = Event::factory()->create([
        'group_id'           => $group->id,
        'created_by_user_id' => $isCreator ? $user->id : null,
    ]);

    expect(Gate::forUser($user)->allows('update', $event))->toBe($expected);
})->with([
    'active admin'               => [['admin'], true, false, false, true],
    'active cpp'                 => [['cpp'], true, false, false, true],
    'inactive admin'             => [['admin'], false, false, false, false],
    'secretary creator'          => [['secretary'], true, false, true, true],
    'secretary group member'     => [['secretary'], true, true, false, true],
    'secretary unrelated'        => [['secretary'], true, false, false, false],
    'priest creator'             => [['priest'], true, false, true, true],
    'priest group member'        => [['priest'], true, true, false, true],
    'priest unrelated'           => [['priest'], true, false, false, false],
    'member group member'        => [['member'], true, true, false, true],
    'member creator only'        => [['member'], true, false, true, false],
    'pascom group member'        => [['pascom'], true, true, true, true],
    'pascom unrelated'           => [['pascom'], true, false, true, false],
    'priest and member in group' => [['priest', 'member'], true, true, false, true],
    'pascom and member in group' => [['pascom', 'member'], true, true, false, true],
    'pascom and admin unrelated' => [['pascom', 'admin'], true, false, false, false],
]);

it('allows active global roles to audit events with or without a group', function (string $role) {
    $user  = User::factory()->create(['roles' => [$role], 'is_active' => true]);
    $group = Group::factory()->create();

    foreach ([$group->id, null] as $groupId) {
        $event = Event::factory()->create(['group_id' => $groupId]);

        expect(Gate::forUser($user)->allows('audit', $event))->toBeTrue();
    }
})->with(['admin', 'priest', 'cpp']);

it('denies inactive users access to event audits even with a global role or coordinator membership', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => false]);
    $group = Group::factory()->create();
    $group->users()->attach($user, ['is_coordinator' => true]);
    $event = Event::factory()->for($group)->create();

    expect(Gate::forUser($user)->allows('audit', $event))->toBeFalse();
});

it('allows coordinators of the organizing group to audit regardless of role', function (string $role) {
    $user  = User::factory()->create(['roles' => [$role], 'is_active' => true]);
    $group = Group::factory()->create();
    $group->users()->attach($user, ['is_coordinator' => true]);
    $event = Event::factory()->for($group)->create();

    expect(Gate::forUser($user)->allows('audit', $event))->toBeTrue();
})->with(['member', 'secretary', 'pascom']);

it('denies auditing to users without coordinator membership of the organizing group', function (string $membership) {
    $user  = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group = Group::factory()->create();

    if ($membership === 'ordinary member') {
        $group->users()->attach($user, ['is_coordinator' => false]);
    }

    if ($membership === 'another group coordinator') {
        Group::factory()->create()->users()->attach($user, ['is_coordinator' => true]);
    }

    $event = Event::factory()->for($group)->create();

    expect(Gate::forUser($user)->allows('audit', $event))->toBeFalse();
})->with(['ordinary member', 'unrelated user', 'another group coordinator']);

it('denies a coordinator without a global role when the event has no organizing group', function () {
    $user = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    Group::factory()->create()->users()->attach($user, ['is_coordinator' => true]);
    $event = Event::factory()->create(['group_id' => null]);

    expect(Gate::forUser($user)->allows('audit', $event))->toBeFalse();
});
