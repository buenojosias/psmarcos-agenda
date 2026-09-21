<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRoleEnum;

class MassPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->hasAnyRole([
            UserRoleEnum::SECRETARY->value,
            UserRoleEnum::CPP->value,
            UserRoleEnum::PRIEST->value,
            UserRoleEnum::ADMIN->value,
        ]);
    }
}
