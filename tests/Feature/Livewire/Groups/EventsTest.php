<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Livewire\Groups\Events;

it('requires authentication to visit group events', function () {
    $this->get(route('groups.events.index', Group::factory()->create()))
        ->assertRedirect(route('login'));
});

it('lists only the group events with their relevant information', function () {
    $group = Group::factory()->create();
    $event = Event::factory()->for($group)->create([
        'name'        => 'Encontro paroquial',
        'type'        => EventTypeEnum::MEETING,
        'starts_at'   => '2026-09-20 09:00:00',
        'ends_at'     => '2026-09-20 11:00:00',
        'is_external' => true,
        'status'      => EventStatusEnum::CONFIRMED,
    ]);
    $event->detail()->create(['external_location_name' => 'Praça central']);
    Event::factory()->for(Group::factory())->create(['name' => 'Evento de outro grupo', 'status' => EventStatusEnum::CONFIRMED]);
    Event::factory()->for($group)->create(['name' => 'Evento excluído', 'status' => EventStatusEnum::CONFIRMED])->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('groups.events.index', $group))
        ->assertSee('Encontro paroquial')
        ->assertSee('Reunião')
        ->assertSee('20/09/2026 09:00')
        ->assertSee('20/09/2026 11:00')
        ->assertSee('Praça central')
        ->assertDontSee('Evento de outro grupo')
        ->assertDontSee('Evento excluído');
});

it('shows status to privileged roles and members of the organizing group', function (array $roles, bool $belongsToGroup) {
    $user  = User::factory()->create(['roles' => $roles]);
    $group = Group::factory()->create();

    if ($belongsToGroup) {
        $group->users()->attach($user, ['is_coordinator' => false]);
    }

    foreach (EventStatusEnum::cases() as $status) {
        Event::factory()->for($group)->create(['name' => 'Evento '.$status->value, 'status' => $status]);
    }

    Livewire::actingAs($user)->test(Events::class, ['group' => $group])
        ->assertSeeInOrder(['Local', 'Status'])
        ->assertSee('Pendente')
        ->assertSee('Evento pending')
        ->assertSee('Evento confirmed')
        ->assertSee('Evento canceled')
        ->assertSee('Evento rescheduled')
        ->assertSee('Evento rejected');
})->with([
    'CPP'                        => [['cpp'], false],
    'Admin'                      => [['admin'], false],
    'Secretary'                  => [['secretary'], false],
    'Priest'                     => [['priest'], false],
    'Member of organizing group' => [['member'], true],
    'Multiple roles'             => [['pascom', 'cpp'], false],
]);

it('lists only confirmed events without status for users without permission', function (array $roles, bool $belongsToGroup) {
    $user            = User::factory()->create(['roles' => $roles]);
    $group           = Group::factory()->create();
    $membershipGroup = $belongsToGroup ? $group : Group::factory()->create();
    $membershipGroup->users()->attach($user, ['is_coordinator' => true]);
    Event::factory()->for($group)->create(['name' => 'Evento visível', 'status' => EventStatusEnum::CONFIRMED]);

    foreach ([EventStatusEnum::PENDING, EventStatusEnum::CANCELED, EventStatusEnum::RESCHEDULED, EventStatusEnum::REJECTED] as $status) {
        Event::factory()->for($group)->create(['name' => 'Evento '.$status->value, 'status' => $status]);
    }

    Livewire::actingAs($user)->test(Events::class, ['group' => $group])
        ->assertSee('Evento visível')
        ->assertDontSee('Status')
        ->assertDontSee('Confirmado')
        ->assertDontSee('Evento pending')
        ->assertDontSee('Evento canceled')
        ->assertDontSee('Evento rescheduled')
        ->assertDontSee('Evento rejected');
})->with([
    'Member of another group'    => [['member'], false],
    'Pascom of organizing group' => [['pascom'], true],
    'Pascom of another group'    => [['pascom'], false],
    'No role'                    => [[], true],
]);

it('shows an empty message when the group has no events', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Events::class, ['group' => Group::factory()->create()])
        ->assertSee('Nenhum evento cadastrado para este grupo.');
});

it('paginates events from newest to oldest and handles missing locations', function () {
    $group = Group::factory()->create();
    Event::factory()->for($group)->count(10)->create(['starts_at' => '2026-10-01 09:00:00', 'status' => EventStatusEnum::CONFIRMED]);
    Event::factory()->for($group)->create(['name' => 'Evento anterior', 'starts_at' => '2026-09-01 09:00:00', 'status' => EventStatusEnum::CONFIRMED]);

    Livewire::actingAs(User::factory()->create())->test(Events::class, ['group' => $group])
        ->assertSee('Local não informado')
        ->assertDontSee('Evento anterior')
        ->call('gotoPage', 2)
        ->assertSee('Evento anterior');
});

it('shows the community of the primary event location', function () {
    $group     = Group::factory()->create();
    $community = Community::create(['name' => 'Comunidade São José', 'alias' => 'sao-jose', 'abbreviation' => 'CSJ']);
    $place     = $community->places()->create(['name' => 'Salão paroquial']);
    $event     = Event::factory()->for($group)->create(['status' => EventStatusEnum::CONFIRMED]);
    $event->reservations()->create([
        'place_id'      => $place->id,
        'reserved_from' => $event->starts_at,
        'reserved_to'   => $event->ends_at,
        'is_primary'    => true,
    ]);

    Livewire::actingAs(User::factory()->create())->test(Events::class, ['group' => $group])
        ->assertSee('Salão paroquial')
        ->assertSee('Comunidade São José');
});
