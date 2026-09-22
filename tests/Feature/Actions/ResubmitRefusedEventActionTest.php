<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use App\Actions\ResubmitRefusedEventAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resubmits a valid internal event and records the status transition', function () {
    Gate::before(static fn (): bool => true);
    $user = User::factory()->create();
    $this->actingAs($user);
    $community = Community::create([
        'name'         => 'Comunidade do reenvio',
        'alias'        => 'resubmit-community',
        'abbreviation' => 'RSC',
    ]);
    $place = $community->places()->create(['name' => 'Igreja']);
    $event = Event::factory()->create([
        'starts_at'              => '2026-10-10 19:00:00',
        'ends_at'                => '2026-10-10 21:00:00',
        'status'                 => EventStatusEnum::REFUSED,
        'is_external'            => false,
        'reservation_hold_until' => '2026-10-12 23:59:00',
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    $note = $event->notes()->create([
        'user_id' => $user->id,
        'content' => 'Ajustar o horário solicitado.',
    ]);

    $result = app(ResubmitRefusedEventAction::class)->handle($event);

    expect($result->status)->toBe(EventStatusEnum::PENDING)
        ->and($result->reservation_hold_until)->toBeNull();
    $this->assertModelExists($note);
    $this->assertDatabaseHas('event_logs', [
        'event_id'    => $event->id,
        'user_id'     => $user->id,
        'action'      => 'submitted',
        'from_status' => EventStatusEnum::REFUSED->value,
        'to_status'   => EventStatusEnum::PENDING->value,
    ]);
});

it('rejects an internal event without exactly one principal reservation', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'status'                 => EventStatusEnum::REFUSED,
        'is_external'            => false,
        'reservation_hold_until' => '2026-10-12 23:59:00',
    ]);

    expect(fn () => app(ResubmitRefusedEventAction::class)->handle($event))
        ->toThrow(ValidationException::class, 'Adicione ao menos uma reserva');

    expect($event->refresh()->status)->toBe(EventStatusEnum::REFUSED)
        ->and($event->reservation_hold_until)->not->toBeNull()
        ->and($event->logs()->exists())->toBeFalse();
});

it('rejects an internal event with more than one principal reservation', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade com duas principais',
        'alias'        => 'resubmit-two-primary',
        'abbreviation' => 'RTP',
    ]);
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'status'      => EventStatusEnum::REFUSED,
        'is_external' => false,
    ]);

    foreach (['Igreja', 'Sala'] as $placeName) {
        PlaceReservation::create([
            'event_id'      => $event->id,
            'place_id'      => $community->places()->create(['name' => $placeName])->id,
            'reserved_from' => '2026-10-10 19:00:00',
            'reserved_to'   => '2026-10-10 21:00:00',
            'is_primary'    => true,
        ]);
    }

    expect(fn () => app(ResubmitRefusedEventAction::class)->handle($event))
        ->toThrow(ValidationException::class, 'exatamente uma reserva principal');

    expect($event->refresh()->status)->toBe(EventStatusEnum::REFUSED)
        ->and($event->logs()->exists())->toBeFalse();
});

it('rejects a principal reservation that does not cover the event', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade sem cobertura',
        'alias'        => 'resubmit-coverage',
        'abbreviation' => 'RCO',
    ]);
    $place = $community->places()->create(['name' => 'Salão']);
    $event = Event::factory()->create([
        'starts_at'              => '2026-10-10 19:00:00',
        'ends_at'                => '2026-10-10 21:00:00',
        'status'                 => EventStatusEnum::REFUSED,
        'is_external'            => false,
        'reservation_hold_until' => '2026-10-12 23:59:00',
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 19:30:00',
        'reserved_to'   => '2026-10-10 21:00:00',
        'is_primary'    => true,
    ]);

    expect(fn () => app(ResubmitRefusedEventAction::class)->handle($event))
        ->toThrow(ValidationException::class, 'cobrir integralmente');

    expect($event->refresh()->status)->toBe(EventStatusEnum::REFUSED)
        ->and($event->reservation_hold_until)->not->toBeNull();
});

it('keeps the refused status and hold when another reservation conflicts', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $community = Community::create([
        'name'         => 'Comunidade com conflito',
        'alias'        => 'resubmit-conflict',
        'abbreviation' => 'RCF',
    ]);
    $place = $community->places()->create(['name' => 'Auditório']);
    $event = Event::factory()->create([
        'starts_at'              => '2026-10-10 19:00:00',
        'ends_at'                => '2026-10-10 21:00:00',
        'status'                 => EventStatusEnum::REFUSED,
        'is_external'            => false,
        'reservation_hold_until' => '2026-10-12 23:59:00',
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    PlaceReservation::create([
        'event_id'      => Event::factory()->create()->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 18:30:00',
        'reserved_to'   => '2026-10-10 20:30:00',
        'is_primary'    => true,
    ]);

    expect(fn () => app(ResubmitRefusedEventAction::class)->handle($event))
        ->toThrow(ValidationException::class, 'precisam ser ajustadas');

    expect($event->refresh()->status)->toBe(EventStatusEnum::REFUSED)
        ->and($event->reservation_hold_until)->not->toBeNull()
        ->and($event->logs()->exists())->toBeFalse();
});

it('resubmits an external event with only the required location details', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'status'      => EventStatusEnum::REFUSED,
        'is_external' => true,
    ]);
    $event->detail()->create([
        'external_location_name'    => 'Centro comunitário',
        'external_location_address' => 'Rua Central, 100',
    ]);

    $result = app(ResubmitRefusedEventAction::class)->handle($event);

    expect($result->status)->toBe(EventStatusEnum::PENDING)
        ->and($result->reservations()->exists())->toBeFalse();
});

it('rejects an external event without its required location details', function () {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'status'      => EventStatusEnum::REFUSED,
        'is_external' => true,
    ]);

    expect(fn () => app(ResubmitRefusedEventAction::class)->handle($event))
        ->toThrow(ValidationException::class, 'Informe o nome do local externo');

    expect($event->refresh()->status)->toBe(EventStatusEnum::REFUSED);
});

it('rejects events that are not refused and canceled events', function (EventStatusEnum $status, string $exception) {
    Gate::before(static fn (): bool => true);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'status'      => $status,
        'is_external' => true,
    ]);

    expect(fn () => app(ResubmitRefusedEventAction::class)->handle($event))
        ->toThrow($exception);

    expect($event->refresh()->status)->toBe($status)
        ->and($event->logs()->exists())->toBeFalse();
})->with([
    'pending'  => [EventStatusEnum::PENDING, ValidationException::class],
    'canceled' => [EventStatusEnum::CANCELED, AuthorizationException::class],
]);

it('forbids resubmission when the user cannot update the event', function () {
    Gate::before(static fn (): bool => false);
    $this->actingAs(User::factory()->create());
    $event = Event::factory()->create([
        'status'      => EventStatusEnum::REFUSED,
        'is_external' => true,
    ]);

    expect(fn () => app(ResubmitRefusedEventAction::class)->handle($event))
        ->toThrow(AuthorizationException::class);

    expect($event->refresh()->status)->toBe(EventStatusEnum::REFUSED)
        ->and($event->logs()->exists())->toBeFalse();
});
