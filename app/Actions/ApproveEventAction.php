<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApproveEventAction
{
    public function __construct(private EventLogAction $eventLog) {}

    public function handle(Event $event, User $user): Event
    {
        return DB::transaction(function () use ($event, $user): Event {
            $event->refresh();

            Gate::forUser($user)->authorize('review', $event);

            $fromStatus = $event->status;
            $event->update([
                'status'                 => EventStatusEnum::CONFIRMED,
                'reservation_hold_until' => null,
            ]);

            $this->eventLog->handle(
                $event,
                EventLogActionEnum::APPROVED,
                $user,
                fromStatus: $fromStatus,
                toStatus: EventStatusEnum::CONFIRMED,
            );

            return $event;
        });
    }
}
