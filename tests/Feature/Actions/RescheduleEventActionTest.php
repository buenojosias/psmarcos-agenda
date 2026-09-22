<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use App\Actions\RescheduleEventAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actionReservationPlace(Community $community, string $name): Place
{
    return $community->places()->create(['name' => $name]);
}

/**
 * @param  list<array{place_id: int, reserved_from: string, reserved_to: string, is_primary: bool}>  $reservations
 */
function runRescheduleAction(
    Event $event,
    string $startsAt,
    string $endsAt,
    array $reservations,
    ?int $communityId,
): Event {
    return app(RescheduleEventAction::class)->handle(
        event: $event,
        startsAt: $startsAt,
        endsAt: $endsAt,
        reservations: $reservations,
        communityId: $communityId,
    );
}

it('persists the reservation proposal and changes a confirmed event to rescheduled', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade Central',
        'alias'        => 'central-action',
        'abbreviation' => 'CCA',
    ]);
    $place = actionReservationPlace($community, 'Salão');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 17:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);

    runRescheduleAction($event, '2026-10-17 20:00:00', '2026-10-17 22:00:00', [[
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-17 18:00:00',
        'reserved_to'   => '2026-10-17 23:00:00',
        'is_primary'    => true,
    ]], $community->id);

    expect($event->refresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-17 20:00:00')
        ->and($event->ends_at->format('Y-m-d H:i:s'))->toBe('2026-10-17 22:00:00')
        ->and($event->status)->toBe(EventStatusEnum::RESCHEDULED);
    $this->assertDatabaseHas('place_reservations', [
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-17 18:00:00',
        'reserved_to'   => '2026-10-17 23:00:00',
        'is_primary'    => true,
    ]);
    $this->assertDatabaseHas('event_logs', [
        'event_id'    => $event->id,
        'action'      => 'rescheduled',
        'from_status' => EventStatusEnum::CONFIRMED->value,
        'to_status'   => EventStatusEnum::RESCHEDULED->value,
    ]);
});

it('rejects a principal reservation that does not cover the event', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade Cobertura',
        'alias'        => 'cobertura-action',
        'abbreviation' => 'COB',
    ]);
    $place = actionReservationPlace($community, 'Igreja');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => false,
    ]);

    expect(fn () => runRescheduleAction($event, '2026-10-17 19:00:00', '2026-10-17 21:00:00', [[
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-17 19:30:00',
        'reserved_to'   => '2026-10-17 21:00:00',
        'is_primary'    => true,
    ]], $community->id))->toThrow(ValidationException::class);

    expect($event->refresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-10 19:00:00')
        ->and($event->reservations()->exists())->toBeFalse();
});

it('rolls back the complete reschedule when final availability validation finds a conflict', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade Conflito',
        'alias'        => 'conflito-action',
        'abbreviation' => 'CON',
    ]);
    $place = actionReservationPlace($community, 'Auditório');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ]);
    $oldReservation = PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    $otherEvent = Event::factory()->create();
    PlaceReservation::create([
        'event_id'      => $otherEvent->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-17 18:30:00',
        'reserved_to'   => '2026-10-17 20:30:00',
        'is_primary'    => true,
    ]);

    expect(fn () => runRescheduleAction($event, '2026-10-17 19:00:00', '2026-10-17 21:00:00', [[
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-17 18:00:00',
        'reserved_to'   => '2026-10-17 22:00:00',
        'is_primary'    => true,
    ]], $community->id))->toThrow(ValidationException::class);

    expect($event->refresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-10 19:00:00')
        ->and($event->status)->toBe(EventStatusEnum::CONFIRMED);
    $this->assertModelExists($oldReservation);
    $this->assertDatabaseMissing('event_logs', ['event_id' => $event->id]);
});

it('keeps pending without an edit or reschedule log', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade Pendente',
        'alias'        => 'pendente-action',
        'abbreviation' => 'PEN',
    ]);
    $place = actionReservationPlace($community, 'Capela');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 20:00:00',
        'status'      => EventStatusEnum::PENDING,
        'is_external' => false,
    ]);

    runRescheduleAction($event, '2026-10-11 19:00:00', '2026-10-11 20:00:00', [[
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-11 18:00:00',
        'reserved_to'   => '2026-10-11 21:00:00',
        'is_primary'    => true,
    ]], $community->id);

    expect($event->refresh()->status)->toBe(EventStatusEnum::PENDING)
        ->and($event->logs()->exists())->toBeFalse();
});

it('keeps refused and logs only the effective update', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade Recusada',
        'alias'        => 'recusada-action',
        'abbreviation' => 'REC',
    ]);
    $place = actionReservationPlace($community, 'Sala');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 20:00:00',
        'status'      => EventStatusEnum::REFUSED,
        'is_external' => false,
    ]);

    runRescheduleAction($event, '2026-10-12 19:00:00', '2026-10-12 20:00:00', [[
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-12 18:00:00',
        'reserved_to'   => '2026-10-12 21:00:00',
        'is_primary'    => true,
    ]], $community->id);

    expect($event->refresh()->status)->toBe(EventStatusEnum::REFUSED);
    $this->assertDatabaseHas('event_logs', [
        'event_id'    => $event->id,
        'action'      => 'updated',
        'from_status' => EventStatusEnum::REFUSED->value,
        'to_status'   => EventStatusEnum::REFUSED->value,
    ]);
});

it('keeps rescheduled and records an updated log', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 20:00:00',
        'status'      => EventStatusEnum::RESCHEDULED,
        'is_external' => true,
    ]);

    app(RescheduleEventAction::class)->handle(
        event: $event,
        startsAt: '2026-10-14 19:00:00',
        endsAt: '2026-10-14 20:00:00',
        externalLocation: [
            'external_location_name'    => 'Auditório externo',
            'external_location_address' => 'Avenida Central, 10',
        ],
    );

    expect($event->refresh()->status)->toBe(EventStatusEnum::RESCHEDULED);
    $this->assertDatabaseHas('event_logs', [
        'event_id'    => $event->id,
        'action'      => 'updated',
        'from_status' => EventStatusEnum::RESCHEDULED->value,
        'to_status'   => EventStatusEnum::RESCHEDULED->value,
    ]);
});

it('updates an external event location without creating reservations', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 20:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => true,
    ]);

    app(RescheduleEventAction::class)->handle(
        event: $event,
        startsAt: '2026-10-13 20:00:00',
        endsAt: '2026-10-13 22:00:00',
        externalLocation: [
            'external_location_name'    => 'Teatro Municipal',
            'external_location_address' => 'Rua Central, 100',
            'external_location_url'     => 'https://example.com/teatro',
        ],
    );

    $detail = $event->refresh()->detail()->firstOrFail();

    expect($event->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-13 20:00:00')
        ->and($event->reservations()->exists())->toBeFalse()
        ->and($detail->external_location_name)->toBe('Teatro Municipal')
        ->and($detail->external_location_address)->toBe('Rua Central, 100')
        ->and($detail->external_location_url)->toBe('https://example.com/teatro');
});

it('changes only the selected occurrence of a recurring event', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'recurrence_code' => 'weekly-action',
        'starts_at'       => '2026-10-10 19:00:00',
        'ends_at'         => '2026-10-10 20:00:00',
        'is_external'     => true,
    ]);
    $otherOccurrence = Event::factory()->create([
        'recurrence_code' => 'weekly-action',
        'starts_at'       => '2026-10-17 19:00:00',
        'ends_at'         => '2026-10-17 20:00:00',
        'is_external'     => true,
    ]);

    app(RescheduleEventAction::class)->handle(
        event: $event,
        startsAt: '2026-10-11 19:00:00',
        endsAt: '2026-10-11 20:00:00',
        externalLocation: [
            'external_location_name'    => 'Casa de encontros',
            'external_location_address' => 'Rua Um, 1',
        ],
    );

    expect($event->refresh()->starts_at->format('Y-m-d'))->toBe('2026-10-11')
        ->and($otherOccurrence->refresh()->starts_at->format('Y-m-d'))->toBe('2026-10-17');
});

it('forbids a user without update permission', function () {
    Gate::before(static fn (): bool => false);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create(['is_external' => true]);

    expect(fn () => app(RescheduleEventAction::class)->handle(
        event: $event,
        startsAt: '2026-10-11 19:00:00',
        endsAt: '2026-10-11 20:00:00',
        externalLocation: [
            'external_location_name'    => 'Local',
            'external_location_address' => 'Endereço',
        ],
    ))->toThrow(AuthorizationException::class);
});
