<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use App\Enums\EventLogActionEnum;
use App\Actions\CreateRecurringEventsAction;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

/** @return array<string, mixed> */
function recurringActionData(Group $group, Community $community, array $placeHours, bool $confirmImmediately = false): array
{
    return [
        'group_id'                   => $group->id,
        'name'                       => 'Encontro recorrente',
        'type'                       => EventTypeEnum::COURSE->value,
        'dates'                      => ['2026-10-10', '2026-10-17'],
        'starts_time'                => '09:00',
        'ends_time'                  => '11:00',
        'is_public'                  => true,
        'confirm_immediately'        => $confirmImmediately,
        'subtitle'                   => '',
        'description'                => '',
        'target_audience'            => '',
        'participation_instructions' => '',
        'registration_required'      => false,
        'registration_url'           => '',
        'registration_deadline'      => '',
        'participation_cost'         => '',
        'contact_name'               => '',
        'contact_phone'              => '',
        'community_id'               => $community->id,
        'place_ids'                  => array_keys($placeHours),
        'place_hours'                => $placeHours,
    ];
}

it('creates the complete pending series with details reservations and shared codes', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group     = Group::factory()->create();
    $community = Community::create([
        'name'         => 'Matriz',
        'alias'        => 'matriz-action',
        'abbreviation' => 'MTZ',
    ]);
    $nave = $community->places()->create(['name' => 'Nave']);
    $hall = $community->places()->create(['name' => 'Salão']);
    $data = recurringActionData($group, $community, [
        $nave->id => ['before' => 0.5, 'after' => 0.25],
        $hall->id => ['before' => 0, 'after' => 1],
    ]);
    $data['subtitle'] = 'Formação paroquial';
    $occurrences      = [
        '2026-10-10' => [
            'date'                => '2026-10-10',
            'starts_at'           => '2026-10-10 09:00:00',
            'ends_at'             => '2026-10-10 11:00:00',
            'remaining_place_ids' => [$nave->id, $hall->id],
            'status'              => 'available',
            'conflicts'           => [],
        ],
        '2026-10-17' => [
            'date'                => '2026-10-17',
            'starts_at'           => '2026-10-17 09:00:00',
            'ends_at'             => '2026-10-17 11:00:00',
            'remaining_place_ids' => [$hall->id],
            'status'              => 'available',
            'conflicts'           => [],
        ],
    ];

    $result = app(CreateRecurringEventsAction::class)->handle($data, $occurrences, $user);

    $events = Event::query()->with(['detail', 'reservations', 'logs'])->orderBy('starts_at')->get();

    expect($result['created'])->toBeTrue()
        ->and($result['events'])->toHaveCount(2)
        ->and($result['conflicts'])->toBe([])
        ->and($events)->toHaveCount(2)
        ->and($events->pluck('recurrence_code')->unique()->values())->toHaveCount(1)
        ->and($events->first()->recurrence_code)->not->toBeNull()
        ->and($events->pluck('status')->all())->toBe([EventStatusEnum::PENDING, EventStatusEnum::PENDING])
        ->and($events->every(fn (Event $event): bool => ! $event->is_external && ! $event->advertisable))->toBeTrue()
        ->and($events->every(fn (Event $event): bool => $event->detail?->subtitle === 'Formação paroquial'))->toBeTrue()
        ->and($events->first()->reservations)->toHaveCount(2)
        ->and($events->last()->reservations)->toHaveCount(1)
        ->and($events->every(fn (Event $event): bool => $event->reservations->where('is_primary', true)->count() === 1))->toBeTrue()
        ->and($events->first()->reservations->firstWhere('place_id', $nave->id)?->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-10 08:30:00')
        ->and($events->first()->reservations->firstWhere('place_id', $nave->id)?->reserved_to->format('Y-m-d H:i:s'))->toBe('2026-10-10 11:15:00')
        ->and($events->pluck('logs')->flatten()->pluck('action')->all())->toBe([
            EventLogActionEnum::CREATED,
            EventLogActionEnum::CREATED,
        ])
        ->and($events->pluck('logs')->flatten()->pluck('operation_code')->unique()->values())->toHaveCount(1);
});

it('confirms every occurrence and records approved logs with one operation code', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group     = Group::factory()->create();
    $community = Community::create([
        'name'         => 'Comunidade Norte',
        'alias'        => 'comunidade-norte-action',
        'abbreviation' => 'CNA',
    ]);
    $place = $community->places()->create(['name' => 'Capela']);
    $data  = recurringActionData($group, $community, [
        $place->id => ['before' => 0, 'after' => 0],
    ], confirmImmediately: true);
    $occurrences = [
        '2026-10-10' => [
            'date'                => '2026-10-10',
            'starts_at'           => '2026-10-10 09:00:00',
            'ends_at'             => '2026-10-10 11:00:00',
            'remaining_place_ids' => [$place->id],
            'status'              => 'available',
            'conflicts'           => [],
        ],
    ];

    app(CreateRecurringEventsAction::class)->handle($data, $occurrences, $user);

    $event = Event::query()->with(['detail', 'logs'])->sole();

    expect($event->status)->toBe(EventStatusEnum::CONFIRMED)
        ->and($event->detail)->toBeNull()
        ->and($event->logs->pluck('action')->all())->toBe([
            EventLogActionEnum::CREATED,
            EventLogActionEnum::APPROVED,
        ])
        ->and($event->logs->pluck('operation_code')->unique()->values())->toHaveCount(1);
});

it('does not persist any occurrence when the final availability check finds a conflict', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group     = Group::factory()->create();
    $community = Community::create([
        'name'         => 'Comunidade Sul',
        'alias'        => 'comunidade-sul-action',
        'abbreviation' => 'CSA',
    ]);
    $place = $community->places()->create(['name' => 'Auditório']);
    $data  = recurringActionData($group, $community, [
        $place->id => ['before' => 0, 'after' => 0],
    ]);
    $occurrences = [
        '2026-10-10' => [
            'date'                => '2026-10-10',
            'starts_at'           => '2026-10-10 09:00:00',
            'ends_at'             => '2026-10-10 11:00:00',
            'remaining_place_ids' => [$place->id],
            'status'              => 'available',
            'conflicts'           => [],
        ],
        '2026-10-17' => [
            'date'                => '2026-10-17',
            'starts_at'           => '2026-10-17 09:00:00',
            'ends_at'             => '2026-10-17 11:00:00',
            'remaining_place_ids' => [$place->id],
            'status'              => 'available',
            'conflicts'           => [],
        ],
    ];
    $existingEvent = Event::factory()->create([
        'starts_at' => '2026-10-17 09:30:00',
        'ends_at'   => '2026-10-17 10:30:00',
    ]);
    PlaceReservation::create([
        'event_id'      => $existingEvent->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-17 09:30:00',
        'reserved_to'   => '2026-10-17 10:30:00',
        'is_primary'    => true,
    ]);

    $result = app(CreateRecurringEventsAction::class)->handle($data, $occurrences, $user);

    expect($result['created'])->toBeFalse()
        ->and($result['events'])->toBe([])
        ->and($result['conflicts'])->toHaveKey('2026-10-17')
        ->and($result['conflicts']['2026-10-17'])->toHaveCount(1);
    $this->assertDatabaseCount('events', 1);
    $this->assertDatabaseCount('place_reservations', 1);
    $this->assertDatabaseCount('event_logs', 0);
});

it('refuses environments from another community', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group     = Group::factory()->create();
    $community = Community::create([
        'name'         => 'Comunidade Leste',
        'alias'        => 'comunidade-leste-action',
        'abbreviation' => 'CLA',
    ]);
    $otherCommunity = Community::create([
        'name'         => 'Comunidade Oeste',
        'alias'        => 'comunidade-oeste-action',
        'abbreviation' => 'COA',
    ]);
    $place = $otherCommunity->places()->create(['name' => 'Sala']);
    $data  = recurringActionData($group, $community, [
        $place->id => ['before' => 0, 'after' => 0],
    ]);
    $occurrences = [
        '2026-10-10' => [
            'date'                => '2026-10-10',
            'starts_at'           => '2026-10-10 09:00:00',
            'ends_at'             => '2026-10-10 11:00:00',
            'remaining_place_ids' => [$place->id],
            'status'              => 'available',
            'conflicts'           => [],
        ],
    ];

    expect(fn () => app(CreateRecurringEventsAction::class)->handle($data, $occurrences, $user))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('events', 0);
});

it('revalidates group and immediate confirmation authorization', function () {
    $member    = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $secretary = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);
    $group     = Group::factory()->create();
    $community = Community::create([
        'name'         => 'Comunidade Central',
        'alias'        => 'comunidade-central-action',
        'abbreviation' => 'CCA',
    ]);
    $place = $community->places()->create(['name' => 'Sala Central']);
    $data  = recurringActionData($group, $community, [
        $place->id => ['before' => 0, 'after' => 0],
    ]);
    $occurrences = [
        '2026-10-10' => [
            'date'                => '2026-10-10',
            'starts_at'           => '2026-10-10 09:00:00',
            'ends_at'             => '2026-10-10 11:00:00',
            'remaining_place_ids' => [$place->id],
            'status'              => 'available',
            'conflicts'           => [],
        ],
    ];

    expect(fn () => app(CreateRecurringEventsAction::class)->handle($data, $occurrences, $member))
        ->toThrow(AuthorizationException::class);

    $data['confirm_immediately'] = true;

    expect(fn () => app(CreateRecurringEventsAction::class)->handle($data, $occurrences, $secretary))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('events', 0);
});
