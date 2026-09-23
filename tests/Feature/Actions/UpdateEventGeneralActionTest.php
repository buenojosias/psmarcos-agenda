<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Actions\EventLogAction;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\Gate;
use App\Actions\UpdateEventGeneralAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('does not persist changes from an unauthorized user', function () {
    Gate::before(static fn (): bool => false);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['name' => 'Nome anterior']);

    actingAs($user);

    expect(fn () => app(UpdateEventGeneralAction::class)->handle($event, ['name' => 'Nome indevido'], $user))
        ->toThrow(AuthorizationException::class);
    expect($event->refresh()->name)->toBe('Nome anterior')
        ->and($event->logs()->count())->toBe(0);
});

it('updates the five permitted fields', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'         => 'Nome anterior',
        'complement'   => null,
        'type'         => EventTypeEnum::MEETING,
        'is_public'    => false,
        'advertisable' => false,
    ]);

    actingAs($user);
    $changed = app(UpdateEventGeneralAction::class)->handle($event, [
        'name'         => 'Nome atualizado',
        'complement'   => 'Partilha e formação',
        'type'         => EventTypeEnum::COURSE->value,
        'is_public'    => true,
        'advertisable' => true,
    ], $user);

    expect($changed)->toBeTrue()
        ->and($event->refresh()->name)->toBe('Nome atualizado')
        ->and($event->complement)->toBe('Partilha e formação')
        ->and($event->type)->toBe(EventTypeEnum::COURSE)
        ->and($event->is_public)->toBeTrue()
        ->and($event->advertisable)->toBeTrue();
});

it('changes advertisable only for the selected recurring occurrence', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'            => 'Ocorrência atual',
        'recurrence_code' => 'weekly-event',
        'advertisable'    => false,
    ]);
    $otherOccurrence = Event::factory()->create([
        'recurrence_code' => 'weekly-event',
        'advertisable'    => false,
    ]);

    actingAs($user);
    app(UpdateEventGeneralAction::class)->handle($event, [
        'name'         => 'Ocorrência atualizada',
        'advertisable' => true,
    ], $user);

    expect($event->refresh()->name)->toBe('Ocorrência atualizada')
        ->and($event->advertisable)->toBeTrue()
        ->and($otherOccurrence->refresh()->advertisable)->toBeFalse();
});

it('does not update a canceled event', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'   => 'Evento cancelado',
        'status' => EventStatusEnum::CANCELED,
    ]);

    actingAs($user);

    expect(fn () => app(UpdateEventGeneralAction::class)->handle($event, ['name' => 'Nome indevido'], $user))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($event->refresh()->name)->toBe('Evento cancelado')
        ->and($event->logs()->count())->toBe(0);
});

it('preserves the event status when updating general information', function (EventStatusEnum $status) {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'   => 'Nome anterior',
        'status' => $status,
    ]);

    actingAs($user);
    app(UpdateEventGeneralAction::class)->handle($event, ['name' => 'Nome atualizado'], $user);

    expect($event->refresh()->status)->toBe($status);
})->with([
    'pending'     => EventStatusEnum::PENDING,
    'confirmed'   => EventStatusEnum::CONFIRMED,
    'rescheduled' => EventStatusEnum::RESCHEDULED,
    'refused'     => EventStatusEnum::REFUSED,
]);

it('logs only the changed fields as updated', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'         => 'Nome anterior',
        'type'         => EventTypeEnum::MEETING,
        'status'       => EventStatusEnum::CONFIRMED,
        'is_public'    => false,
        'advertisable' => false,
    ]);

    actingAs($user);
    app(UpdateEventGeneralAction::class)->handle($event, [
        'name'         => 'Nome atualizado',
        'type'         => EventTypeEnum::MEETING->value,
        'is_public'    => false,
        'advertisable' => false,
    ], $user);

    $log = $event->logs()->sole();

    expect($log->action)->toBe(EventLogActionEnum::UPDATED)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->changes)->toBe([
            'name' => [
                'old' => 'Nome anterior',
                'new' => 'Nome atualizado',
            ],
        ]);
});

it('updates pending general information without logging it as updated', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'   => 'Nome anterior',
        'status' => EventStatusEnum::PENDING,
    ]);
    app(EventLogAction::class)->handle(
        event: $event,
        action: EventLogActionEnum::CREATED,
        user: $user,
        changes: [],
    );

    actingAs($user);
    $changed = app(UpdateEventGeneralAction::class)->handle($event, ['name' => 'Nome atualizado'], $user);

    expect($changed)->toBeTrue()
        ->and($event->refresh()->name)->toBe('Nome atualizado')
        ->and($event->logs()->where('action', EventLogActionEnum::CREATED)->count())->toBe(1)
        ->and($event->logs()->where('action', EventLogActionEnum::UPDATED)->exists())->toBeFalse();
});

it('logs updated general information for reviewed statuses', function (EventStatusEnum $status) {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'   => 'Nome anterior',
        'status' => $status,
    ]);

    actingAs($user);
    app(UpdateEventGeneralAction::class)->handle($event, ['name' => 'Nome atualizado'], $user);

    expect($event->logs()->where('action', EventLogActionEnum::UPDATED)->count())->toBe(1);
})->with([
    'confirmed'   => EventStatusEnum::CONFIRMED,
    'rescheduled' => EventStatusEnum::RESCHEDULED,
    'refused'     => EventStatusEnum::REFUSED,
]);

it('does not log when no general information changed', function (EventStatusEnum $status) {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['status' => $status]);

    actingAs($user);
    $changed = app(UpdateEventGeneralAction::class)->handle($event, [
        'name'         => $event->name,
        'type'         => $event->type->value,
        'is_public'    => $event->is_public,
        'advertisable' => $event->advertisable,
    ], $user);

    expect($changed)->toBeFalse()
        ->and($event->logs()->count())->toBe(0);
})->with([
    'pending'     => EventStatusEnum::PENDING,
    'confirmed'   => EventStatusEnum::CONFIRMED,
    'rescheduled' => EventStatusEnum::RESCHEDULED,
    'refused'     => EventStatusEnum::REFUSED,
]);
