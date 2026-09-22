<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use App\Actions\CreateEventReservationAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createReservationActionPlace(string $name = 'Salão'): Place
{
    $community = Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);

    return $community->places()->create(['name' => $name]);
}

function internalReservationEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_merge([
        'starts_at'   => '2026-10-10 10:00:00',
        'ends_at'     => '2026-10-10 11:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ], $attributes));
}

it('creates the first reservation as primary and allows it to start on the previous day', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = internalReservationEvent();
    $place = createReservationActionPlace();

    $reservation = app(CreateEventReservationAction::class)->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-09 18:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
    ], $user);

    expect($reservation->event_id)->toBe($event->id)
        ->and($reservation->place_id)->toBe($place->id)
        ->and($reservation->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-09 18:00:00')
        ->and($reservation->is_primary)->toBeTrue();
});

it('allows multiple reservations of the same place in distinct intervals', function () {
    Gate::before(static fn (): bool => true);
    $user   = User::factory()->create();
    $event  = internalReservationEvent();
    $place  = createReservationActionPlace();
    $action = app(CreateEventReservationAction::class);

    $action->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
    ], $user);
    $second = $action->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 08:00:00',
        'reserved_to'   => '2026-10-10 09:00:00',
    ], $user);

    expect($event->reservations()->count())->toBe(2)
        ->and($second->is_primary)->toBeFalse();
});

it('rejects an invalid reservation interval', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = internalReservationEvent();
    $place = createReservationActionPlace();

    expect(fn () => app(CreateEventReservationAction::class)->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 11:00:00',
        'reserved_to'   => '2026-10-10 10:00:00',
    ], $user))->toThrow(ValidationException::class);

    expect($event->reservations()->exists())->toBeFalse();
});

it('rejects a conflicting reservation with a friendly interval message', function () {
    Gate::before(static fn (): bool => true);
    $user       = User::factory()->create();
    $event      = internalReservationEvent();
    $otherEvent = internalReservationEvent();
    $place      = createReservationActionPlace('Salão paroquial');
    PlaceReservation::create([
        'event_id'      => $otherEvent->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 09:30:00',
        'reserved_to'   => '2026-10-10 11:30:00',
        'is_primary'    => true,
    ]);

    try {
        app(CreateEventReservationAction::class)->handle($event, [
            'place_id'      => $place->id,
            'reserved_from' => '2026-10-10 09:00:00',
            'reserved_to'   => '2026-10-10 12:00:00',
        ], $user);
    } catch (ValidationException $exception) {
        expect($exception->errors()['place_id'][0])
            ->toContain('Salão paroquial')
            ->toContain('2026-10-10 09:30:00')
            ->toContain('2026-10-10 11:30:00');
    }

    expect($event->reservations()->exists())->toBeFalse();
});

it('rejects a first reservation that does not cover the event', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = internalReservationEvent();
    $place = createReservationActionPlace();

    expect(fn () => app(CreateEventReservationAction::class)->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 10:30:00',
        'reserved_to'   => '2026-10-10 12:00:00',
    ], $user))->toThrow(ValidationException::class);
});

it('rejects reservations for external events', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = internalReservationEvent(['is_external' => true]);
    $place = createReservationActionPlace();

    expect(fn () => app(CreateEventReservationAction::class)->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
    ], $user))->toThrow(AuthorizationException::class);
});

it('rejects reservations from a user without update permission', function () {
    Gate::before(static fn (): bool => false);
    $user  = User::factory()->create();
    $event = internalReservationEvent();
    $place = createReservationActionPlace();

    expect(fn () => app(CreateEventReservationAction::class)->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
    ], $user))->toThrow(AuthorizationException::class);
});

it('rejects reservations for canceled events', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = internalReservationEvent(['status' => EventStatusEnum::CANCELED]);
    $place = createReservationActionPlace();

    expect(fn () => app(CreateEventReservationAction::class)->handle($event, [
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
    ], $user))->toThrow(AuthorizationException::class);
});
