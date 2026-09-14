<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Group;
use App\Enums\UserRoleEnum;

class GroupPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && ($this->assignUser($user) || $user->getRolesArray() === [UserRoleEnum::MEMBER->value]);
    }

    public function assignUser(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::CPP->value,
            UserRoleEnum::PASCOM->value,
            UserRoleEnum::SECRETARY->value,
        ]);
    }

    public function manageUsers(User $user, Group $group): bool
    {
        return $user->is_active && ($user->hasAnyRole([
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::CPP->value,
            UserRoleEnum::SECRETARY->value,
        ]) || $group->users()->whereKey($user->id)->wherePivot('is_coordinator', true)->exists());
    }
}
