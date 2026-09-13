<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Support\Facades\Gate;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-users');
    }

    public function create(User $actor): bool
    {
        return Gate::forUser($actor)->allows('manage-users');
    }

    public function update(User $actor, User $user): bool
    {
        return Gate::forUser($actor)->allows('manage-users')
            && $actor->isNot($user)
            && ($actor->hasRole(UserRoleEnum::ADMIN->value) || ! $user->hasRole(UserRoleEnum::ADMIN->value));
    }

    public function delete(User $actor, User $user): bool
    {
        return Gate::forUser($actor)->allows('manage-users')
            && $actor->isNot($user)
            && ($actor->hasRole(UserRoleEnum::ADMIN->value) || ! $user->hasRole(UserRoleEnum::ADMIN->value));
    }
}
