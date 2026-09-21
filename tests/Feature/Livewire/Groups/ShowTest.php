<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Enums\EventTypeEnum;
use App\Enums\GroupTypeEnum;
use App\Livewire\Groups\Show;
use App\Enums\EventStatusEnum;
use App\Livewire\Groups\Users;

it('shows group details and linked users with a leader badge', function () {
    $group  = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP, 'description' => 'Encontros semanais']);
    $leader = User::factory()->create(['name' => 'Maria Líder']);
    $member = User::factory()->create(['name' => 'João Membro']);
    $group->users()->attach($leader, ['is_coordinator' => true]);
    $group->users()->attach($member);

    $this->actingAs(User::factory()->create())->get(route('groups.show', $group))
        ->assertOk()->assertSee(['Grupo Esperança', 'Encontros semanais', 'Maria Líder', 'João Membro', 'Líder']);
});

it('counts each requested event status and includes canceled events in the total', function () {
    $group      = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP]);
    $otherGroup = Group::create(['name' => 'Outro Grupo', 'slug' => 'outro-grupo', 'type' => GroupTypeEnum::GROUP]);

    foreach ([
        EventStatusEnum::CONFIRMED,
        EventStatusEnum::REJECTED,
        EventStatusEnum::PENDING,
        EventStatusEnum::RESCHEDULED,
        EventStatusEnum::CANCELED,
    ] as $status) {
        Event::create([
            'group_id'  => $group->id,
            'name'      => 'Evento '.$status->value,
            'type'      => EventTypeEnum::MEETING,
            'status'    => $status,
            'starts_at' => now(),
            'ends_at'   => now()->addHour(),
        ]);
    }

    Event::create([
        'group_id'  => $otherGroup->id,
        'name'      => 'Evento de outro grupo',
        'type'      => EventTypeEnum::MEETING,
        'status'    => EventStatusEnum::CONFIRMED,
        'starts_at' => now(),
        'ends_at'   => now()->addHour(),
    ]);

    $component = Livewire::actingAs(User::factory()->create())->test(Show::class, ['group' => $group]);

    $component->assertSee(['Status dos eventos', 'Aprovados', 'Recusados', 'Pendentes', 'Remarcados', 'Total']);
    expect($component->get('group')->confirmed_events_count)->toBe(1)
        ->and($component->get('group')->rejected_events_count)->toBe(1)
        ->and($component->get('group')->pending_events_count)->toBe(1)
        ->and($component->get('group')->rescheduled_events_count)->toBe(1)
        ->and($component->get('group')->events_count)->toBe(5);
});

it('links and unlinks users and refreshes the component list', function (string $role) {
    $actor    = User::factory()->create(['roles' => [$role], 'is_active' => true]);
    $group    = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP]);
    $selected = User::factory()->create(['name' => 'Maria Silva']);

    Livewire::actingAs($actor)->test(Users::class, ['group' => $group])
        ->set('userId', $selected->id)->set('isLeader', true)->call('addUser')
        ->assertHasNoErrors()->assertSee('Maria Silva')->assertSee('Líder')
        ->call('removeUser', $selected->id)->assertDontSee('Maria Silva');

    expect($group->users()->count())->toBe(0);
})->with(['admin', 'cpp', 'secretary']);

it('allows a leader of this group to manage users', function () {
    $leader = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group  = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP]);
    $group->users()->attach($leader, ['is_coordinator' => true]);
    $selected = User::factory()->create();

    Livewire::actingAs($leader)->test(Users::class, ['group' => $group])
        ->set('userId', $selected->id)->call('addUser')->assertHasNoErrors();

    expect($group->users()->whereKey($selected->id)->exists())->toBeTrue();
});

it('forbids unauthorized link changes even through crafted Livewire actions', function () {
    $actor    = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group    = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP]);
    $selected = User::factory()->create();
    $group->users()->attach($selected);
    $otherGroup = Group::create(['name' => 'Outro Grupo', 'slug' => 'outro-grupo', 'type' => GroupTypeEnum::GROUP]);
    $otherGroup->users()->attach($actor, ['is_coordinator' => true]);

    Livewire::actingAs($actor)->test(Users::class, ['group' => $group])
        ->assertDontSee('Vincular usuário')->assertDontSee('Desvincular')
        ->set('userId', $actor->id)->call('addUser')->assertForbidden();

    Livewire::actingAs($actor)->test(Users::class, ['group' => $group])
        ->call('removeUser', $selected->id)->assertForbidden();

    expect($group->users()->count())->toBe(1);
});

it('rejects duplicate and nonexistent users', function () {
    $actor    = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group    = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP]);
    $selected = User::factory()->create();
    $group->users()->attach($selected);

    Livewire::actingAs($actor)->test(Users::class, ['group' => $group])
        ->set('userId', $selected->id)->call('addUser')->assertHasErrors(['userId']);
    Livewire::actingAs($actor)->test(Users::class, ['group' => $group])
        ->set('userId', 999999)->call('addUser')->assertHasErrors(['userId' => 'exists']);
});

it('restricts member search to managers of the specific group', function () {
    $actor    = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $group    = Group::create(['name' => 'Grupo Esperança', 'slug' => 'grupo-esperanca', 'type' => GroupTypeEnum::GROUP]);
    $selected = User::factory()->create(['name' => 'Maria Silva']);

    $this->actingAs($actor)->getJson(route('groups.members.search', $group))->assertForbidden();
    $group->users()->attach($actor, ['is_coordinator' => true]);
    $this->actingAs($actor)->getJson(route('groups.members.search', $group))
        ->assertOk()->assertJsonFragment(['label' => 'Maria Silva', 'value' => $selected->id]);
});
