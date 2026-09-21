<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use App\Actions\ApproveEventAction;
use Illuminate\Auth\Access\AuthorizationException;

it('confirms a reviewable event and records its approval', function () {
    $event = Event::factory()->create([
        'status'                 => EventStatusEnum::RESCHEDULED,
        'reservation_hold_until' => now()->addDay(),
    ]);
    $user = User::factory()->create(['roles' => ['admin']]);

    $approvedEvent = app(ApproveEventAction::class)->handle($event, $user);

    expect($approvedEvent->status)->toBe(EventStatusEnum::CONFIRMED)
        ->and($approvedEvent->reservation_hold_until)->toBeNull();
    $this->assertDatabaseHas('event_logs', [
        'event_id'    => $event->id,
        'user_id'     => $user->id,
        'action'      => EventLogActionEnum::APPROVED->value,
        'from_status' => EventStatusEnum::RESCHEDULED->value,
        'to_status'   => EventStatusEnum::CONFIRMED->value,
    ]);
});

it('does not approve an event when the user cannot review it', function () {
    $event = Event::factory()->create(['status' => EventStatusEnum::PENDING]);
    $user  = User::factory()->create(['roles' => ['member']]);

    expect(fn () => app(ApproveEventAction::class)->handle($event, $user))
        ->toThrow(AuthorizationException::class);

    expect($event->refresh()->status)->toBe(EventStatusEnum::PENDING);
    $this->assertDatabaseCount('event_logs', 0);
});
