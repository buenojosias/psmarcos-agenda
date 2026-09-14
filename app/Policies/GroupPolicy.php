<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
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
}
