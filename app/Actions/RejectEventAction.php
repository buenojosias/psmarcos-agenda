<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RejectEventAction
{
    public function __construct(private EventLogAction $eventLog) {}

    public function handle(Event $event, User $user, string $reason): Event
    {
        return DB::transaction(function () use ($event, $user, $reason): Event {
            $event->refresh();

            Gate::forUser($user)->authorize('review', $event);

            $fromStatus = $event->status;
            $event->notes()->create([
                'user_id' => $user->id,
                'content' => $reason,
            ]);
            $event->update([
                'status'                 => EventStatusEnum::REJECTED,
                'reservation_hold_until' => now()->addDays(7),
            ]);

            $this->eventLog->handle(
                $event,
                EventLogActionEnum::REJECTED,
                $user,
                fromStatus: $fromStatus,
                toStatus: EventStatusEnum::REJECTED,
            );

            return $event;
        });
    }
}
