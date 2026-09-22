<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Enums\UserRoleEnum;
use App\Enums\EventStatusEnum;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active === true;
    }

    public function selectGroup(User $user, Group $group): bool
    {
        return $this->create($user)
            && (! $user->isMemberOnly() || $user->groups()->whereKey($group->id)->exists());
    }

    public function confirmImmediately(User $user): bool
    {
        return $this->create($user)
            && $user->hasAnyRole([
                UserRoleEnum::CPP->value,
                UserRoleEnum::PRIEST->value,
                UserRoleEnum::ADMIN->value,
            ]);
    }

    public function viewAny(User $user): bool
    {
        return $user->is_active === true;
    }

    public function view(User $user, Event $event): Response
    {
        return Event::query()->visibleTo($user)->whereKey($event->id)->exists()
                ? Response::allow()
                : Response::denyAsNotFound();
    }

    public function update(User $user, Event $event): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->hasRole(UserRoleEnum::PASCOM->value)) {
            return $this->belongsToEventGroup($user, $event);
        }

        if ($user->hasAnyRole([UserRoleEnum::ADMIN->value, UserRoleEnum::CPP->value])) {
            return true;
        }

        if ($user->hasAnyRole([UserRoleEnum::SECRETARY->value, UserRoleEnum::PRIEST->value])) {
            return $this->isCreator($user, $event) || $this->belongsToEventGroup($user, $event);
        }

        return $this->belongsToEventGroup($user, $event);
    }

    public function manage(User $user, Event $event): bool
    {
        return ! $user->hasRole(UserRoleEnum::PASCOM->value) && $this->isCreator($user, $event);
    }

    public function review(User $user, Event $event): bool
    {
        return ! $user->hasRole(UserRoleEnum::PASCOM->value)
            && $user->hasAnyRole([UserRoleEnum::CPP->value, UserRoleEnum::PRIEST->value, UserRoleEnum::ADMIN->value])
            && in_array($event->status, [EventStatusEnum::PENDING, EventStatusEnum::RESCHEDULED], true);
    }

    private function isCreator(User $user, Event $event): bool
    {
        return $event->created_by_user_id === $user->id;
    }

    private function belongsToEventGroup(User $user, Event $event): bool
    {
        return $event->group_id !== null
            && $user->groups()->whereKey($event->group_id)->exists();
    }
}
