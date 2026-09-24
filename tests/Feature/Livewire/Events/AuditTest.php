<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\EventLog;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;

it('requires authentication to access the event audit', function () {
    $event = Event::factory()->create();

    $this->get(route('events.audit', $event))->assertRedirect(route('login'));
});

it('shows the event heading, link, and empty audit message to an authorized user', function () {
    $event = Event::factory()->create(['name' => 'Encontro paroquial']);
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    $this->actingAs($user)->get(route('events.audit', $event))
        ->assertOk()
        ->assertSee('Auditoria do evento')
        ->assertSee('Encontro paroquial')
        ->assertSee('Histórico de alterações e decisões realizadas sobre este evento.')
        ->assertSee('Nenhum registro de auditoria encontrado para este evento.')
        ->assertSee('← Voltar ao evento')
        ->assertSee('href="'.route('events.show', $event).'"', false);
});

it('forbids the event audit to a user without audit permission', function () {
    $event = Event::factory()->create();
    $user  = User::factory()->create(['roles' => ['member'], 'is_active' => true]);

    $this->actingAs($user)->get(route('events.audit', $event))->assertForbidden();
});

it('shows only the current occurrence logs in descending order with action, user, time, and status labels', function () {
    $user  = User::factory()->create(['name' => 'João da Silva', 'roles' => ['admin'], 'is_active' => true]);
    $event = Event::factory()->create(['recurrence_code' => 'serie-de-setembro']);
    $other = Event::factory()->create(['recurrence_code' => 'serie-de-setembro']);

    EventLog::query()->forceCreate([
        'event_id'   => $event->id, 'user_id' => $user->id,
        'action'     => EventLogActionEnum::CREATED, 'to_status' => EventStatusEnum::PENDING,
        'created_at' => '2026-09-22 09:15:00',
    ]);
    EventLog::query()->forceCreate([
        'event_id'    => $event->id, 'user_id' => $user->id,
        'action'      => EventLogActionEnum::APPROVED,
        'from_status' => EventStatusEnum::PENDING, 'to_status' => EventStatusEnum::CONFIRMED,
        'created_at'  => '2026-09-22 10:35:00',
    ]);
    EventLog::query()->forceCreate([
        'event_id'   => $other->id, 'user_id' => $user->id,
        'action'     => EventLogActionEnum::REFUSED,
        'created_at' => '2026-09-22 11:00:00',
    ]);

    $this->actingAs($user)->get(route('events.audit', $event))
        ->assertSeeInOrder(['Evento aprovado', 'Evento criado'])
        ->assertSee('22/09/2026 às 10:35')
        ->assertSee('22/09/2026 às 09:15')
        ->assertSee('João da Silva')
        ->assertSee('Pendente → Confirmado')
        ->assertSee('Status: Pendente')
        ->assertDontSee('Evento recusado')
        ->assertDontSee('serie-de-setembro');
});

it('shows a fallback when the log user no longer exists and never invents a status transition', function () {
    $viewer = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event  = Event::factory()->create();

    EventLog::query()->forceCreate([
        'event_id' => $event->id, 'user_id' => null,
        'action'   => EventLogActionEnum::UPDATED, 'from_status' => EventStatusEnum::PENDING,
    ]);

    $this->actingAs($viewer)->get(route('events.audit', $event))
        ->assertSee('Usuário não disponível')
        ->assertSee('Status anterior: Pendente')
        ->assertDontSee('Pendente →');
});

it('groups consecutive logs with the same operation while preserving each action and transition', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event = Event::factory()->create();

    EventLog::query()->forceCreate([
        'event_id'       => $event->id, 'user_id' => $user->id,
        'operation_code' => 'same-operation', 'action' => EventLogActionEnum::CREATED,
        'to_status'      => EventStatusEnum::PENDING, 'created_at' => '2026-09-22 09:15:00',
    ]);
    EventLog::query()->forceCreate([
        'event_id'       => $event->id, 'user_id' => $user->id,
        'operation_code' => 'same-operation', 'action' => EventLogActionEnum::APPROVED,
        'from_status'    => EventStatusEnum::PENDING, 'to_status' => EventStatusEnum::CONFIRMED,
        'created_at'     => '2026-09-22 09:15:00',
    ]);

    $response = $this->actingAs($user)->get(route('events.audit', $event))
        ->assertSee('Evento criado')
        ->assertSee('Evento aprovado')
        ->assertSee('Status: Pendente')
        ->assertSee('Pendente → Confirmado')
        ->assertDontSee('same-operation');

    expect(mb_substr_count($response->getContent(), '22/09/2026 às 09:15'))->toBe(1);
});

it('keeps logs without an operation code separate and only groups adjacent matching operations', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event = Event::factory()->create();

    foreach ([
        [EventLogActionEnum::CREATED, 'same-operation', '2026-09-22 09:15:00'],
        [EventLogActionEnum::UPDATED, null, '2026-09-22 09:14:00'],
        [EventLogActionEnum::APPROVED, 'same-operation', '2026-09-22 09:13:00'],
        [EventLogActionEnum::SUBMITTED, null, '2026-09-22 09:12:00'],
        [EventLogActionEnum::CANCELED, null, '2026-09-22 09:11:00'],
    ] as [$action, $operationCode, $createdAt]) {
        EventLog::query()->forceCreate([
            'event_id'       => $event->id, 'user_id' => $user->id,
            'operation_code' => $operationCode, 'action' => $action,
            'created_at'     => $createdAt,
        ]);
    }

    $response = $this->actingAs($user)->get(route('events.audit', $event))
        ->assertSeeInOrder(['Evento criado', 'Evento atualizado', 'Evento aprovado', 'Enviado para aprovação', 'Evento cancelado']);

    expect(mb_substr_count($response->getContent(), 'audit-entry-'))->toBe(5);
});

it('shows friendly expandable changes while preserving the timeline', function () {
    $viewer             = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event              = Event::factory()->create();
    $oldCommunity       = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $newCommunity       = Community::create(['name' => 'Capela', 'alias' => 'capela', 'abbreviation' => 'CP']);
    $missingCommunityId = $newCommunity->id + 100;

    EventLog::query()->forceCreate([
        'event_id' => $event->id,
        'user_id'  => $viewer->id,
        'action'   => EventLogActionEnum::UPDATED,
        'changes'  => [
            'starts_at'    => ['old' => '2026-10-18 19:30:00', 'new' => '2026-10-19 20:00:00'],
            'community_id' => ['old' => $oldCommunity->id, 'new' => $newCommunity->id],
            'advertisable' => ['old' => false, 'new' => true],
        ],
    ]);
    EventLog::query()->forceCreate([
        'event_id' => $event->id,
        'user_id'  => $viewer->id,
        'action'   => EventLogActionEnum::UPDATED,
        'changes'  => ['community_id' => ['old' => $newCommunity->id, 'new' => $missingCommunityId]],
    ]);

    $this->actingAs($viewer)->get(route('events.audit', $event))
        ->assertSee('Evento atualizado')
        ->assertSee('Ver alterações')
        ->assertSee('x-data="{ open: false }"', false)
        ->assertSee('x-cloak x-show="open"', false)
        ->assertSee('Início')
        ->assertSee('Antes: 18/10/2026 19:30')
        ->assertSee('Depois: 19/10/2026 20:00')
        ->assertSee('Antes: Matriz')
        ->assertSee('Depois: Capela')
        ->assertSee('Comunidade não disponível')
        ->assertSee('Depois: Sim')
        ->assertDontSee('community_id')
        ->assertDontSee('Depois: '.$missingCommunityId);
});

it('escapes user-provided change values in the audit details', function () {
    $viewer = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event  = Event::factory()->create();

    EventLog::query()->forceCreate([
        'event_id' => $event->id,
        'user_id'  => $viewer->id,
        'action'   => EventLogActionEnum::UPDATED,
        'changes'  => ['description' => ['old' => null, 'new' => '<script>alert(1)</script>']],
    ]);

    $this->actingAs($viewer)->get(route('events.audit', $event))
        ->assertSee('Descrição')
        ->assertSee('<script>alert(1)</script>')
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('compares reservations by place and time and uses the parent name for subplaces', function () {
    $viewer    = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event     = Event::factory()->create();
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $church    = $community->places()->create(['name' => 'Igreja']);
    $sacristy  = $church->subplaces()->create(['name' => 'Sacristia', 'community_id' => $community->id]);
    $room      = $community->places()->create(['name' => 'Sala 2']);
    $hall      = $community->places()->create(['name' => 'Salão paroquial']);

    EventLog::query()->forceCreate([
        'event_id' => $event->id,
        'user_id'  => $viewer->id,
        'action'   => EventLogActionEnum::UPDATED,
        'changes'  => ['reservations' => [
            'old' => [
                ['place_id' => $sacristy->id, 'reserved_from' => '2026-10-18 18:30:00', 'reserved_to' => '2026-10-18 21:00:00', 'is_primary' => true],
                ['place_id' => $room->id, 'reserved_from' => '2026-10-18 19:00:00', 'reserved_to' => '2026-10-18 21:00:00', 'is_primary' => false],
            ],
            'new' => [
                ['place_id' => $hall->id, 'reserved_from' => '2026-10-19 18:00:00', 'reserved_to' => '2026-10-19 22:00:00', 'is_primary' => false],
                ['place_id' => $sacristy->id, 'reserved_from' => '2026-10-19 19:00:00', 'reserved_to' => '2026-10-19 21:30:00', 'is_primary' => true],
            ],
        ]],
    ]);

    $this->actingAs($viewer)->get(route('events.audit', $event))
        ->assertSee('Igreja: Sacristia · Alterada')
        ->assertSee('Antes: 18/10/2026 18:30 — 21:00 · Principal')
        ->assertSee('Depois: 19/10/2026 19:00 — 21:30 · Principal')
        ->assertSee('Sala 2 · Removida')
        ->assertSee('Salão paroquial · Adicionada')
        ->assertSee('Depois: 19/10/2026 18:00 — 22:00')
        ->assertDontSee('place_id');
});
