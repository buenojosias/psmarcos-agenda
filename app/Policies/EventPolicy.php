<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Event;
use App\Enums\UserRoleEnum;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Auth\Access\Response;

class EventPolicy
{
    public function view(User $user, Event $event): Response
    {
        return $event->status === EventStatusEnum::CONFIRMED
            || $user->hasRole(UserRoleEnum::PASCOM->value)
            || $this->isCreator($user, $event)
            || $this->review($user, $event)
            || $user->groups()->whereKey($event->group_id)->exists()
                ? Response::allow()
                : Response::denyAsNotFound();
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
        return $event->logs()->where('action', EventLogActionEnum::CREATED->value)
            ->orderBy('created_at')->orderBy('id')->value('user_id') === $user->id;
    }
}
