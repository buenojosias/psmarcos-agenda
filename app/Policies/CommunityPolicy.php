<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Community;
use App\Enums\UserRoleEnum;

class CommunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active === true;
    }

    public function view(User $user, Community $community): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active === true && $user->hasAnyRole([
            UserRoleEnum::ADMIN->value,
            UserRoleEnum::CPP->value,
        ]);
    }

    public function update(User $user, Community $community): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Community $community): bool
    {
        return $this->create($user);
    }
}
