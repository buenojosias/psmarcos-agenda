<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Enums\EventStatusEnum;
use App\Actions\EventLogAction;
use App\Enums\EventLogActionEnum;

it('creates an event log with status context changes and operation code', function () {
    $event = Event::factory()->create();
    $user  = User::factory()->create();

    $log = app(EventLogAction::class)->handle(
        $event,
        EventLogActionEnum::UPDATED,
        $user,
        fromStatus: EventStatusEnum::PENDING,
        toStatus: EventStatusEnum::RESCHEDULED,
        changes: ['name' => ['before' => 'Antigo', 'after' => 'Novo']],
        operationCode: '01K5MY7YQW7S8Q7P9G6S4K3J2H',
    );

    expect($log->event_id)->toBe($event->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->action)->toBe(EventLogActionEnum::UPDATED)
        ->and($log->from_status)->toBe(EventStatusEnum::PENDING)
        ->and($log->to_status)->toBe(EventStatusEnum::RESCHEDULED)
        ->and($log->changes)->toBe(['name' => ['before' => 'Antigo', 'after' => 'Novo']])
        ->and($log->operation_code)->toBe('01K5MY7YQW7S8Q7P9G6S4K3J2H');
});
