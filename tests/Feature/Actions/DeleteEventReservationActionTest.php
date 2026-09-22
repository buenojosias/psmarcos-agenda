<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use App\Actions\DeleteEventReservationAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function deleteReservationActionPlace(): Place
{
    $community = Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);

    return $community->places()->create(['name' => fake()->unique()->word()]);
}

function deleteReservationEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'starts_at'   => '2026-10-10 10:00:00',
        'ends_at'     => '2026-10-10 11:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ], $attributes));
}

function deletableReservation(Event $event, Place $place, bool $primary): PlaceReservation
{
    return PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
        'is_primary'    => $primary,
    ]);
}

it('deletes a non-primary reservation when another reservation remains', function () {
    Gate::before(static fn (): bool => true);
    $user      = User::factory()->create();
    $event     = deleteReservationEvent();
    $primary   = deletableReservation($event, deleteReservationActionPlace(), true);
    $secondary = deletableReservation($event, deleteReservationActionPlace(), false);

    app(DeleteEventReservationAction::class)->handle($event, $secondary, $user);

    expect($secondary->fresh())->toBeNull()
        ->and($primary->refresh()->is_primary)->toBeTrue()
        ->and($event->reservations()->count())->toBe(1);
});

it('does not delete the primary reservation while other reservations exist', function () {
    Gate::before(static fn (): bool => true);
    $user    = User::factory()->create();
    $event   = deleteReservationEvent();
    $primary = deletableReservation($event, deleteReservationActionPlace(), true);
    deletableReservation($event, deleteReservationActionPlace(), false);

    expect(fn () => app(DeleteEventReservationAction::class)->handle($event, $primary, $user))
        ->toThrow(ValidationException::class);

    expect($event->reservations()->count())->toBe(2);
});

it('does not leave an internal event without reservations', function () {
    Gate::before(static fn (): bool => true);
    $user        = User::factory()->create();
    $event       = deleteReservationEvent();
    $reservation = deletableReservation($event, deleteReservationActionPlace(), true);

    expect(fn () => app(DeleteEventReservationAction::class)->handle($event, $reservation, $user))
        ->toThrow(ValidationException::class);

    expect($reservation->refresh()->exists())->toBeTrue();
});

it('rejects deleting a reservation from another event', function () {
    Gate::before(static fn (): bool => true);
    $user        = User::factory()->create();
    $event       = deleteReservationEvent();
    $otherEvent  = deleteReservationEvent();
    $reservation = deletableReservation($otherEvent, deleteReservationActionPlace(), true);

    expect(fn () => app(DeleteEventReservationAction::class)->handle($event, $reservation, $user))
        ->toThrow(AuthorizationException::class);
});

it('revalidates delete authorization and event state', function (bool $authorized, array $eventAttributes) {
    Gate::before(static fn (): bool => $authorized);
    $user        = User::factory()->create();
    $event       = deleteReservationEvent($eventAttributes);
    $reservation = deletableReservation($event, deleteReservationActionPlace(), true);

    expect(fn () => app(DeleteEventReservationAction::class)->handle($event, $reservation, $user))
        ->toThrow(AuthorizationException::class);
})->with([
    'unauthorized user' => [false, []],
    'external event'    => [true, ['is_external' => true]],
    'canceled event'    => [true, ['status' => EventStatusEnum::CANCELED]],
]);
