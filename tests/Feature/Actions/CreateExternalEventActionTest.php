<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;
use App\Actions\CreateExternalEventAction;
use Illuminate\Auth\Access\AuthorizationException;

/** @return array<string, mixed> */
function externalEventCreationData(Group $group, bool $confirmImmediately = false): array
{
    return [
        'group_id'                   => $group->id,
        'name'                       => 'Encontro de formação',
        'type'                       => EventTypeEnum::COURSE->value,
        'starts_at'                  => '2026-10-10T09:00',
        'ends_at'                    => '2026-10-10T11:00',
        'is_public'                  => true,
        'advertisable'               => true,
        'confirm_immediately'        => $confirmImmediately,
        'external_location_name'     => 'Centro de convenções',
        'external_location_address'  => 'Rua das Flores, 100',
        'external_location_url'      => 'https://example.com/local',
        'complement'                 => 'Formação paroquial',
        'description'                => '<p>Descrição do encontro.</p>',
        'target_audience'            => 'Coordenadores',
        'participation_instructions' => 'Chegar com antecedência.',
        'registration_required'      => true,
        'registration_url'           => 'https://example.com/inscricao',
        'registration_deadline'      => '2026-10-08',
        'participation_cost'         => 'Gratuito',
        'contact_name'               => 'Maria',
        'contact_phone'              => '(11) 99999-9999',
    ];
}

it('creates a pending external event with its location details and log', function () {
    $group = Group::factory()->create();
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    $event = app(CreateExternalEventAction::class)->handle(externalEventCreationData($group), $user);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->status)->toBe(EventStatusEnum::PENDING)
        ->and($event->is_external)->toBeTrue()
        ->and($event->complement)->toBe('Formação paroquial')
        ->and($event->advertisable)->toBeTrue()
        ->and($event->reservations()->exists())->toBeFalse()
        ->and($event->detail->only([
            'external_location_name',
            'external_location_address',
            'external_location_url',
            'description',
        ]))->toBe([
            'external_location_name'    => 'Centro de convenções',
            'external_location_address' => 'Rua das Flores, 100',
            'external_location_url'     => 'https://example.com/local',
            'description'               => '<p>Descrição do encontro.</p>',
        ]);

    $log = $event->logs()->sole();

    expect($log->action)->toBe(EventLogActionEnum::CREATED)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->to_status)->toBe(EventStatusEnum::PENDING)
        ->and($log->operation_code)->not->toBeNull();
    $this->assertDatabaseCount('place_reservations', 0);
});

it('creates a confirmed external event and approval log for an authorized user', function () {
    $group = Group::factory()->create();
    $user  = User::factory()->create(['roles' => ['cpp'], 'is_active' => true]);

    $event = app(CreateExternalEventAction::class)->handle(externalEventCreationData($group, confirmImmediately: true), $user);
    $logs  = $event->logs()->orderBy('id')->get();

    expect($event->status)->toBe(EventStatusEnum::CONFIRMED)
        ->and($logs->pluck('action')->all())->toBe([
            EventLogActionEnum::CREATED,
            EventLogActionEnum::APPROVED,
        ])
        ->and($logs[1]->from_status)->toBe(EventStatusEnum::PENDING)
        ->and($logs[1]->to_status)->toBe(EventStatusEnum::CONFIRMED)
        ->and($logs[0]->operation_code)->toBe($logs[1]->operation_code);
});

it('refuses immediate confirmation from an unauthorized user without persistence', function () {
    $group = Group::factory()->create();
    $user  = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);

    expect(fn () => app(CreateExternalEventAction::class)->handle(externalEventCreationData($group, confirmImmediately: true), $user))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('place_reservations', 0);
    $this->assertDatabaseCount('event_logs', 0);
});
