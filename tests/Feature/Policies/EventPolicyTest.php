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
