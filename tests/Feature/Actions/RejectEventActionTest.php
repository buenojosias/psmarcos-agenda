<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use App\Actions\RejectEventAction;
use Illuminate\Auth\Access\AuthorizationException;

it('rejects a reviewable event, records its reason, and retains its reservations', function () {
    $this->freezeTime();

    $event     = Event::factory()->create(['status' => EventStatusEnum::PENDING]);
    $user      = User::factory()->create(['roles' => ['priest']]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão principal']);
    $event->reservations()->create([
        'place_id'      => $place->id,
        'reserved_from' => now()->addDay(),
        'reserved_to'   => now()->addDay()->addHour(),
    ]);

    $rejectedEvent = app(RejectEventAction::class)->handle($event, $user, 'O horário conflita com outro evento.');

    expect($rejectedEvent->status)->toBe(EventStatusEnum::REJECTED)
        ->and($rejectedEvent->reservation_hold_until->toDateTimeString())->toBe(now()->addDays(7)->toDateTimeString())
        ->and($rejectedEvent->reservations)->toHaveCount(1)
        ->and($rejectedEvent->notes()->sole()->content)->toBe('O horário conflita com outro evento.')
        ->and($rejectedEvent->notes()->sole()->user_id)->toBe($user->id);
    $this->assertDatabaseHas('event_logs', [
        'event_id'    => $event->id,
        'user_id'     => $user->id,
        'action'      => EventLogActionEnum::REJECTED->value,
        'from_status' => EventStatusEnum::PENDING->value,
        'to_status'   => EventStatusEnum::REJECTED->value,
    ]);
});

it('does not reject an event when the user cannot review it', function () {
    $event = Event::factory()->create(['status' => EventStatusEnum::PENDING]);
    $user  = User::factory()->create(['roles' => ['member']]);

    expect(fn () => app(RejectEventAction::class)->handle($event, $user, 'Motivo da recusa.'))
        ->toThrow(AuthorizationException::class);

    expect($event->refresh()->status)->toBe(EventStatusEnum::PENDING);
    $this->assertDatabaseCount('event_notes', 0);
    $this->assertDatabaseCount('event_logs', 0);
});
