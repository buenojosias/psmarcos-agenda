<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use App\Enums\EventLogActionEnum;
use App\Actions\CreateEventAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * @return array{data: array<string, mixed>, places: list<Place>}
 */
function eventCreationData(bool $confirmImmediately = false): array
{
    $community = Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);
    $group = Group::factory()->create();
    $nave  = $community->places()->create(['name' => 'Nave']);
    $hall  = $community->places()->create(['name' => 'Salão']);

    return [
        'data' => [
            'group_id'                   => $group->id,
            'name'                       => 'Encontro de formação',
            'type'                       => EventTypeEnum::COURSE->value,
            'starts_at'                  => '2026-10-10T09:00',
            'ends_at'                    => '2026-10-10T11:00',
            'is_public'                  => true,
            'advertisable'               => true,
            'confirm_immediately'        => $confirmImmediately,
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
            'community_id'               => $community->id,
            'place_ids'                  => [$nave->id, $hall->id],
            'place_hours'                => [
                $nave->id => ['before' => 0.5, 'after' => 0.25],
                $hall->id => ['before' => 1.25, 'after' => 0.5],
            ],
        ],
        'places' => [$nave, $hall],
    ];
}

it('creates a pending internal occasional event with details reservations and log', function () {
    ['data' => $data, 'places' => [$nave, $hall]] = eventCreationData();
    $user                                         = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    $result = app(CreateEventAction::class)->handle($data, $user);
    $event  = $result['event'];

    expect($result['created'])->toBeTrue()
        ->and($result['conflicts'])->toBe([])
        ->and($event)->toBeInstanceOf(Event::class)
        ->and($event->community_id)->toBe($data['community_id'])
        ->and($event->status)->toBe(EventStatusEnum::PENDING)
        ->and($event->is_external)->toBeFalse()
        ->and($event->complement)->toBe('Formação paroquial');
    expect($event->detail->only([
        'description',
        'target_audience',
        'participation_instructions',
        'registration_required',
        'registration_url',
        'participation_cost',
        'contact_name',
        'contact_phone',
    ]))->toBe([
        'description'                => '<p>Descrição do encontro.</p>',
        'target_audience'            => 'Coordenadores',
        'participation_instructions' => 'Chegar com antecedência.',
        'registration_required'      => true,
        'registration_url'           => 'https://example.com/inscricao',
        'participation_cost'         => 'Gratuito',
        'contact_name'               => 'Maria',
        'contact_phone'              => '(11) 99999-9999',
    ]);

    $reservations = $event->reservations()->orderBy('id')->get();

    expect($reservations)->toHaveCount(2)
        ->and($reservations->pluck('place_id')->all())->toBe([$nave->id, $hall->id])
        ->and($reservations->where('is_primary', true))->toHaveCount(1)
        ->and($reservations[0]->is_primary)->toBeTrue()
        ->and($reservations[0]->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-10 08:30:00')
        ->and($reservations[0]->reserved_to->format('Y-m-d H:i:s'))->toBe('2026-10-10 11:15:00')
        ->and($reservations[1]->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-10 07:45:00')
        ->and($reservations[1]->reserved_to->format('Y-m-d H:i:s'))->toBe('2026-10-10 11:30:00');

    $log = $event->logs()->sole();

    expect($log->action)->toBe(EventLogActionEnum::CREATED)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->to_status)->toBe(EventStatusEnum::PENDING)
        ->and($log->operation_code)->not->toBeNull();
});

it('does not create event details when every detail field is effectively empty', function () {
    ['data' => $data] = eventCreationData();
    $user             = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $data             = [
        ...$data,
        'complement'                 => '',
        'description'                => '<p><br></p><p>&nbsp;</p>',
        'target_audience'            => '',
        'participation_instructions' => '',
        'registration_required'      => false,
        'registration_url'           => '',
        'registration_deadline'      => '',
        'participation_cost'         => '',
        'contact_name'               => '',
        'contact_phone'              => '',
    ];

    $result = app(CreateEventAction::class)->handle($data, $user);

    expect($result['created'])->toBeTrue()
        ->and($result['event'])->toBeInstanceOf(Event::class)
        ->and($result['event']->complement)->toBeNull()
        ->and($result['event']->detail()->exists())->toBeFalse();
    $this->assertDatabaseCount('events', 1);
    $this->assertDatabaseCount('event_details', 0);
});

it('creates a confirmed event and approval log for an authorized user', function () {
    ['data' => $data] = eventCreationData(confirmImmediately: true);
    $user             = User::factory()->create(['roles' => ['cpp'], 'is_active' => true]);

    $result = app(CreateEventAction::class)->handle($data, $user);
    $event  = $result['event'];
    $logs   = $event->logs()->orderBy('id')->get();

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
    ['data' => $data] = eventCreationData(confirmImmediately: true);
    $user             = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);

    expect(fn () => app(CreateEventAction::class)->handle($data, $user))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('place_reservations', 0);
    $this->assertDatabaseCount('event_logs', 0);
});

it('rechecks group authorization before persisting', function () {
    ['data' => $data] = eventCreationData();
    $user             = User::factory()->create(['roles' => ['member'], 'is_active' => true]);

    expect(fn () => app(CreateEventAction::class)->handle($data, $user))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('place_reservations', 0);
    $this->assertDatabaseCount('event_logs', 0);
});

it('refuses places from a different community without persistence', function () {
    ['data' => $data] = eventCreationData();
    $user             = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $otherCommunity   = Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);
    $data['community_id'] = $otherCommunity->id;

    expect(fn () => app(CreateEventAction::class)->handle($data, $user))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('place_reservations', 0);
    $this->assertDatabaseCount('event_logs', 0);
});

it('returns final conflicts without persisting any part of the requested event', function () {
    ['data' => $data, 'places' => [$nave]] = eventCreationData();
    $user                                  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $existingEvent                         = Event::factory()->create([
        'starts_at' => '2026-10-10 08:00:00',
        'ends_at'   => '2026-10-10 10:00:00',
    ]);
    PlaceReservation::create([
        'event_id'      => $existingEvent->id,
        'place_id'      => $nave->id,
        'reserved_from' => '2026-10-10 08:00:00',
        'reserved_to'   => '2026-10-10 10:00:00',
        'is_primary'    => true,
    ]);
    $countsBefore = [
        'events'             => Event::count(),
        'event_details'      => DB::table('event_details')->count(),
        'place_reservations' => PlaceReservation::count(),
        'event_logs'         => DB::table('event_logs')->count(),
    ];

    $result = app(CreateEventAction::class)->handle($data, $user);

    expect($result['created'])->toBeFalse()
        ->and($result['event'])->toBeNull()
        ->and($result['conflicts'])->toHaveCount(1);
    expect([
        'events'             => Event::count(),
        'event_details'      => DB::table('event_details')->count(),
        'place_reservations' => PlaceReservation::count(),
        'event_logs'         => DB::table('event_logs')->count(),
    ])->toBe($countsBefore);
});
