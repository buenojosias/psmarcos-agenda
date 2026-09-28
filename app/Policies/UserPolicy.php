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
        return $this->viewAny($actor);
    }

    public function view(User $actor, User $user): bool
    {
        return $this->viewAny($actor)
            && ($actor->hasRole(UserRoleEnum::ADMIN->value) || ! $user->hasRole(UserRoleEnum::ADMIN->value));
    }

    public function update(User $actor, User $user): bool
    {
        return $this->view($actor, $user)
            && ($actor->hasRole(UserRoleEnum::ADMIN->value) || ! $user->hasRole(UserRoleEnum::CPP->value) || $actor->hasRole(UserRoleEnum::CPP->value));
    }

    public function updateRoles(User $actor, User $user): bool
    {
        return $this->update($actor, $user);
    }

    public function approve(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $user->isPending();
    }

    public function setActive(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $user->isPending();
    }

    public function delete(User $actor, User $user): bool
    {
        return false;
    }
}
