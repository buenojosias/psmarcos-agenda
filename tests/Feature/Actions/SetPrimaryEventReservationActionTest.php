<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use App\Actions\SetPrimaryEventReservationAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function primaryReservationActionPlace(): Place
{
    $community = Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);

    return $community->places()->create(['name' => fake()->unique()->word()]);
}

function primaryReservationEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'starts_at'   => '2026-10-10 10:00:00',
        'ends_at'     => '2026-10-10 11:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ], $attributes));
}

function primaryCandidate(
    Event $event,
    Place $place,
    string $from,
    string $to,
    bool $primary,
): PlaceReservation {
    return PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => $from,
        'reserved_to'   => $to,
        'is_primary'    => $primary,
    ]);
}

it('atomically changes the primary reservation and leaves exactly one primary', function () {
    Gate::before(static fn (): bool => true);
    $user    = User::factory()->create();
    $event   = primaryReservationEvent();
    $current = primaryCandidate(
        $event,
        primaryReservationActionPlace(),
        '2026-10-10 09:00:00',
        '2026-10-10 12:00:00',
        true,
    );
    $new = primaryCandidate(
        $event,
        primaryReservationActionPlace(),
        '2026-10-09 18:00:00',
        '2026-10-10 13:00:00',
        false,
    );

    app(SetPrimaryEventReservationAction::class)->handle($event, $new, $user);

    expect($current->refresh()->is_primary)->toBeFalse()
        ->and($new->refresh()->is_primary)->toBeTrue()
        ->and($event->reservations()->where('is_primary', true)->count())->toBe(1);
});

it('rejects a primary reservation that does not cover the event', function () {
    Gate::before(static fn (): bool => true);
    $user    = User::factory()->create();
    $event   = primaryReservationEvent();
    $current = primaryCandidate(
        $event,
        primaryReservationActionPlace(),
        '2026-10-10 09:00:00',
        '2026-10-10 12:00:00',
        true,
    );
    $invalid = primaryCandidate(
        $event,
        primaryReservationActionPlace(),
        '2026-10-10 10:30:00',
        '2026-10-10 12:00:00',
        false,
    );

    expect(fn () => app(SetPrimaryEventReservationAction::class)->handle($event, $invalid, $user))
        ->toThrow(ValidationException::class);

    expect($current->refresh()->is_primary)->toBeTrue()
        ->and($invalid->refresh()->is_primary)->toBeFalse();
});

it('rejects a primary candidate from another event', function () {
    Gate::before(static fn (): bool => true);
    $user       = User::factory()->create();
    $event      = primaryReservationEvent();
    $otherEvent = primaryReservationEvent();
    $candidate  = primaryCandidate(
        $otherEvent,
        primaryReservationActionPlace(),
        '2026-10-10 09:00:00',
        '2026-10-10 12:00:00',
        true,
    );

    expect(fn () => app(SetPrimaryEventReservationAction::class)->handle($event, $candidate, $user))
        ->toThrow(AuthorizationException::class);
});

it('revalidates primary change authorization and event state', function (bool $authorized, array $eventAttributes) {
    Gate::before(static fn (): bool => $authorized);
    $user      = User::factory()->create();
    $event     = primaryReservationEvent($eventAttributes);
    $candidate = primaryCandidate(
        $event,
        primaryReservationActionPlace(),
        '2026-10-10 09:00:00',
        '2026-10-10 12:00:00',
        true,
    );

    expect(fn () => app(SetPrimaryEventReservationAction::class)->handle($event, $candidate, $user))
        ->toThrow(AuthorizationException::class);
})->with([
    'unauthorized user' => [false, []],
    'external event'    => [true, ['is_external' => true]],
    'canceled event'    => [true, ['status' => EventStatusEnum::CANCELED]],
]);
