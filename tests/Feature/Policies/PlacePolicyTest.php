<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Place;
use App\Enums\UserRoleEnum;
use Illuminate\Support\Facades\Gate;

it('allows only active Admin CPP and Secretary users to create spaces', function (UserRoleEnum $role, bool $active) {
    $user    = User::factory()->make(['roles' => [$role->value], 'is_active' => $active]);
    $allowed = $active && in_array($role, [UserRoleEnum::ADMIN, UserRoleEnum::CPP, UserRoleEnum::SECRETARY], true);

    expect(Gate::forUser($user)->allows('create', Place::class))->toBe($allowed);
})->with(UserRoleEnum::cases())->with([true, false]);

it('forbids guests from creating spaces', function () {
    expect(Gate::allows('create', Place::class))->toBeFalse();
});

it('allows only active Admin CPP and Secretary users to update spaces', function (UserRoleEnum $role, bool $active) {
    $user    = User::factory()->make(['roles' => [$role->value], 'is_active' => $active]);
    $allowed = $active && in_array($role, [UserRoleEnum::ADMIN, UserRoleEnum::CPP, UserRoleEnum::SECRETARY], true);

    expect(Gate::forUser($user)->allows('update', new Place))->toBe($allowed);
})->with(UserRoleEnum::cases())->with([true, false]);
