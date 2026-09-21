<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Models\EventLog;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;

class EventLogAction
{
    /**
     * @param  array<string, mixed>|null  $changes
     */
    public function handle(
        Event $event,
        EventLogActionEnum $action,
        User $user,
        ?EventStatusEnum $fromStatus = null,
        ?EventStatusEnum $toStatus = null,
        ?array $changes = null,
        ?string $operationCode = null,
    ): EventLog {
        return $event->logs()->create([
            'user_id'        => $user->id,
            'operation_code' => $operationCode,
            'action'         => $action,
            'from_status'    => $fromStatus,
            'to_status'      => $toStatus,
            'changes'        => $changes,
        ]);
    }
}
