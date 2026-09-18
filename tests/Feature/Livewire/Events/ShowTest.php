<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Livewire\Events\Show;
use App\Enums\EventStatusEnum;
use App\Enums\EventLogActionEnum;

it('requires authentication', function () {
    $this->get(route('events.show', Event::factory()->create()))->assertRedirect(route('login'));
});

it('displays event details and the reservation period', function () {
    $event = Event::factory()->create(['status' => EventStatusEnum::CONFIRMED, 'starts_at' => '2026-10-02 10:00', 'ends_at' => '2026-10-02 12:00']);
    $event->detail()->create(['description' => '<script>alert(1)</script>', 'contact_name' => 'Maria', 'registration_required' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão principal']);
    $event->reservations()->create(['place_id' => $place->id, 'reserved_from' => '2026-10-02 09:00', 'reserved_to' => '2026-10-02 13:00']);

    $this->actingAs(User::factory()->create())->get(route('events.show', $event))
        ->assertOk()->assertSee($event->name)->assertSee('Maria')
        ->assertSee('Salão principal')->assertSee('Matriz')
        ->assertSee('02/10/2026 09:00')->assertSee('02/10/2026 13:00')
        ->assertSee('02/10/2026 10:00')->assertSee('02/10/2026 12:00')
        ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
});

it('shows management buttons only to the creator', function () {
    $creator = User::factory()->create(['roles' => ['member']]);
    $group   = Group::factory()->create();
    $group->users()->attach($creator);
    $event = Event::factory()->for($group)->create(['status' => EventStatusEnum::PENDING]);
    $event->logs()->create(['user_id' => $creator->id, 'action' => EventLogActionEnum::CREATED]);

    Livewire::actingAs($creator)->test(Show::class, ['event' => $event])
        ->assertSee('Editar')->assertSee('Remarcar')->assertSee('Cancelar')
        ->assertDontSee('Aprovar')->assertDontSee('Recusar');
});

it('shows review buttons to reviewers for pending and rescheduled events', function (string $role, EventStatusEnum $status) {
    $event = Event::factory()->create(['status' => $status]);

    Livewire::actingAs(User::factory()->create(['roles' => [$role]]))->test(Show::class, ['event' => $event])
        ->assertSee('Aprovar')->assertSee('Recusar')->assertDontSee('Editar')->assertDontSee('Remarcar');
})->with(['cpp', 'priest', 'admin'])->with([EventStatusEnum::PENDING, EventStatusEnum::RESCHEDULED]);

it('hides review buttons for other statuses', function (EventStatusEnum $status) {
    $group = Group::factory()->create();
    $user  = User::factory()->create(['roles' => ['admin']]);
    $group->users()->attach($user);
    $event = Event::factory()->for($group)->create(['status' => $status]);

    Livewire::actingAs($user)->test(Show::class, ['event' => $event])
        ->assertDontSee('Aprovar')->assertDontSee('Recusar');
})->with([EventStatusEnum::CONFIRMED, EventStatusEnum::CANCELED, EventStatusEnum::REJECTED]);

it('allows pascom without action buttons even when also creator and admin', function () {
    $user  = User::factory()->create(['roles' => ['pascom', 'admin']]);
    $event = Event::factory()->create(['status' => EventStatusEnum::PENDING]);
    $event->logs()->create(['user_id' => $user->id, 'action' => EventLogActionEnum::CREATED]);

    Livewire::actingAs($user)->test(Show::class, ['event' => $event])
        ->assertSee($event->name)->assertDontSee('Editar')->assertDontSee('Remarcar')
        ->assertDontSee('Cancelar')->assertDontSee('Aprovar')->assertDontSee('Recusar');
});

it('allows a linked user to view a nonconfirmed event without buttons', function () {
    $group = Group::factory()->create();
    $user  = User::factory()->create(['roles' => ['member']]);
    $group->users()->attach($user);
    $event = Event::factory()->for($group)->create(['status' => EventStatusEnum::PENDING]);

    Livewire::actingAs($user)->test(Show::class, ['event' => $event])
        ->assertSee($event->name)->assertDontSee('Editar')->assertDontSee('Aprovar');
});

it('returns 404 for an unrelated user viewing a nonconfirmed event', function (string $role) {
    $user  = User::factory()->create(['roles' => [$role]]);
    $event = Event::factory()->for(Group::factory())->create(['status' => EventStatusEnum::PENDING]);
    Group::factory()->create()->users()->attach($user);
    $event->logs()->create(['user_id' => $user->id, 'action' => EventLogActionEnum::UPDATED]);

    $this->actingAs($user)->get(route('events.show', $event))->assertNotFound();
})->with(['member']);

it('rechecks visibility when group membership is removed', function () {
    $group = Group::factory()->create();
    $user  = User::factory()->create(['roles' => ['member']]);
    $group->users()->attach($user);
    $event     = Event::factory()->for($group)->create(['status' => EventStatusEnum::PENDING]);
    $component = Livewire::actingAs($user)->test(Show::class, ['event' => $event]);
    $group->users()->detach($user);

    $component->call('$refresh')->assertNotFound();
});

it('lists the primary space first even when other reservations start earlier', function () {
    $event     = Event::factory()->create(['status' => EventStatusEnum::CONFIRMED]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);

    foreach ([
        ['name' => 'Cozinha', 'is_primary' => false, 'hour' => '08:00'],
        ['name' => 'Auditório', 'is_primary' => true, 'hour' => '10:00'],
        ['name' => 'Sala de apoio', 'is_primary' => false, 'hour' => '09:00'],
    ] as $space) {
        $place = $community->places()->create(['name' => $space['name']]);
        $event->reservations()->create([
            'place_id'      => $place->id,
            'is_primary'    => $space['is_primary'],
            'reserved_from' => '2026-10-02 '.$space['hour'],
            'reserved_to'   => '2026-10-02 12:00',
        ]);
    }

    Livewire::actingAs(User::factory()->create())->test(Show::class, ['event' => $event])
        ->assertSeeInOrder(['Auditório', 'Espaço principal', 'Cozinha', 'Sala de apoio']);
});
