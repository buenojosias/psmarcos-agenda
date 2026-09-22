<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use App\Actions\UpdateEventReservationAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function updateReservationActionPlace(string $name = 'Salão', ?Place $main = null): Place
{
    $community = $main?->community ?? Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);

    return $community->places()->create([
        'name'          => $name,
        'main_place_id' => $main?->id,
    ]);
}

function updateReservationEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'starts_at'   => '2026-10-10 10:00:00',
        'ends_at'     => '2026-10-10 11:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ], $attributes));
}

function eventReservation(
    Event $event,
    Place $place,
    string $from,
    string $to,
    bool $primary = false,
): PlaceReservation {
    return PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => $from,
        'reserved_to'   => $to,
        'is_primary'    => $primary,
    ]);
}

it('ignores only the reservation being updated and preserves its primary flag', function () {
    Gate::before(static fn (): bool => true);
    $user        = User::factory()->create();
    $event       = updateReservationEvent();
    $place       = updateReservationActionPlace();
    $reservation = eventReservation($event, $place, '2026-10-10 09:00:00', '2026-10-10 12:00:00', true);

    $updated = app(UpdateEventReservationAction::class)->handle($event, $reservation, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 08:00:00',
        'reserved_to'   => '2026-10-10 13:00:00',
    ], $user);

    expect($updated->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-10 08:00:00')
        ->and($updated->reserved_to->format('Y-m-d H:i:s'))->toBe('2026-10-10 13:00:00')
        ->and($updated->is_primary)->toBeTrue();
});

it('still detects another reservation from the same event', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = updateReservationEvent();
    $place = updateReservationActionPlace();
    eventReservation($event, $place, '2026-10-10 09:00:00', '2026-10-10 12:00:00', true);
    $edited = eventReservation($event, $place, '2026-10-10 12:00:00', '2026-10-10 13:00:00');

    expect(fn () => app(UpdateEventReservationAction::class)->handle($event, $edited, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 11:30:00',
        'reserved_to'   => '2026-10-10 13:00:00',
    ], $user))->toThrow(ValidationException::class);

    expect($edited->refresh()->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-10 12:00:00');
});

it('detects a parent place conflict when editing a child reservation', function () {
    Gate::before(static fn (): bool => true);
    $user       = User::factory()->create();
    $event      = updateReservationEvent();
    $otherEvent = updateReservationEvent();
    $primary    = updateReservationActionPlace('Igreja');
    $parent     = updateReservationActionPlace('Centro catequético');
    $child      = updateReservationActionPlace('Sala 1', $parent);
    eventReservation($event, $primary, '2026-10-10 09:00:00', '2026-10-10 12:00:00', true);
    $edited = eventReservation($event, $child, '2026-10-10 08:00:00', '2026-10-10 09:00:00');
    eventReservation($otherEvent, $parent, '2026-10-10 12:00:00', '2026-10-10 14:00:00', true);

    expect(fn () => app(UpdateEventReservationAction::class)->handle($event, $edited, [
        'place_id'      => $child->id,
        'reserved_from' => '2026-10-10 12:30:00',
        'reserved_to'   => '2026-10-10 13:30:00',
    ], $user))->toThrow(ValidationException::class);
});

it('requires the primary reservation to continue covering the event', function () {
    Gate::before(static fn (): bool => true);
    $user        = User::factory()->create();
    $event       = updateReservationEvent();
    $place       = updateReservationActionPlace();
    $reservation = eventReservation($event, $place, '2026-10-10 09:00:00', '2026-10-10 12:00:00', true);

    expect(fn () => app(UpdateEventReservationAction::class)->handle($event, $reservation, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 10:30:00',
        'reserved_to'   => '2026-10-10 12:00:00',
    ], $user))->toThrow(ValidationException::class);

    expect($reservation->refresh()->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-10 09:00:00');
});

it('rejects a reservation from another event', function () {
    Gate::before(static fn (): bool => true);
    $user        = User::factory()->create();
    $event       = updateReservationEvent();
    $otherEvent  = updateReservationEvent();
    $place       = updateReservationActionPlace();
    $reservation = eventReservation($otherEvent, $place, '2026-10-10 09:00:00', '2026-10-10 12:00:00', true);

    expect(fn () => app(UpdateEventReservationAction::class)->handle($event, $reservation, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 08:00:00',
        'reserved_to'   => '2026-10-10 13:00:00',
    ], $user))->toThrow(AuthorizationException::class);
});

it('revalidates update authorization and event state', function (bool $authorized, array $eventAttributes) {
    Gate::before(static fn (): bool => $authorized);
    $user        = User::factory()->create();
    $event       = updateReservationEvent($eventAttributes);
    $place       = updateReservationActionPlace();
    $reservation = eventReservation($event, $place, '2026-10-10 09:00:00', '2026-10-10 12:00:00', true);

    expect(fn () => app(UpdateEventReservationAction::class)->handle($event, $reservation, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 08:00:00',
        'reserved_to'   => '2026-10-10 13:00:00',
    ], $user))->toThrow(AuthorizationException::class);
})->with([
    'unauthorized user' => [false, []],
    'external event'    => [true, ['is_external' => true]],
    'canceled event'    => [true, ['status' => EventStatusEnum::CANCELED]],
]);
