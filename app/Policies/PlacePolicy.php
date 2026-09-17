<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Place;
use App\Enums\UserRoleEnum;

class PlacePolicy
{
    public function delete(User $user, Place $place): bool
    {
        return $this->create($user);
    }

    public function update(User $user, Place $place): bool
    {
        return $this->create($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active === true && $user->hasAnyRole([
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::CPP->value,
            UserRoleEnum::SECRETARY->value,
        ]);
    }
}
