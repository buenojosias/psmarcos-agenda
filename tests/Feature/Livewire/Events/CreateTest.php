<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use Illuminate\Support\Facades\Gate;
use App\Livewire\Events\Forms\Occasional;

function occasionalEventData(Group $group): array
{
    return [
        'group_id'            => $group->id,
        'name'                => 'Encontro de formação',
        'type'                => EventTypeEnum::COURSE->value,
        'starts_at'           => '2026-10-10T09:00',
        'ends_at'             => '2026-10-10T11:00',
        'is_public'           => true,
        'advertisable'        => false,
        'confirm_immediately' => false,
    ];
}

function eventCommunity(): Community
{
    return Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);
}

it('renders the internal occasional event form at the create route', function () {
    $user = User::factory()->create(['roles' => ['member'], 'is_active' => true]);

    $this->actingAs($user)->get(route('events.create'))
        ->assertSee('Cadastrar evento')
        ->assertSee('Dados do evento')
        ->assertSee('Ambientes');
});

it('forbids inactive users from accessing or creating events', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => false]);

    $this->actingAs($user)->get(route('events.create'))->assertForbidden();
    expect(Gate::forUser($user)->denies('create', Event::class))->toBeTrue();
});

it('allows all active users to create events and limits immediate confirmation by role', function (string $role, bool $canConfirm) {
    $user = User::factory()->make(['roles' => [$role], 'is_active' => true]);

    expect(Gate::forUser($user)->allows('create', Event::class))->toBeTrue();
    expect(Gate::forUser($user)->allows('confirmImmediately', Event::class))->toBe($canConfirm);
})->with([
    ['member', false],
    ['secretary', false],
    ['pascom', false],
    ['cpp', true],
    ['priest', true],
    ['admin', true],
]);

it('shows only a member exclusive user groups and enforces that restriction on validation', function () {
    $user      = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $allowed   = Group::factory()->create(['name' => 'Grupo vinculado']);
    $unrelated = Group::factory()->create(['name' => 'Grupo não vinculado']);
    $user->groups()->attach($allowed);

    Livewire::actingAs($user)->test(Occasional::class)
        ->assertSee('Grupo vinculado')
        ->assertDontSee('Grupo não vinculado')
        ->set(occasionalEventData($unrelated))
        ->call('validateDraft')
        ->assertForbidden();

    Livewire::actingAs($user)->test(Occasional::class)
        ->set(occasionalEventData($allowed))
        ->call('validateDraft')
        ->assertHasNoErrors();
});

it('allows a user with another role to select any group', function () {
    $user  = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);
    $group = Group::factory()->create(['name' => 'Grupo disponível']);

    Livewire::actingAs($user)->test(Occasional::class)
        ->assertSee('Grupo disponível')
        ->set(occasionalEventData($group))
        ->call('validateDraft')
        ->assertHasNoErrors();
});

it('does not expose immediate confirmation to unauthorized users and protects it in the backend', function () {
    $user  = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);
    $group = Group::factory()->create();

    Livewire::actingAs($user)->test(Occasional::class)
        ->assertDontSee('Confirmar imediatamente')
        ->set([...occasionalEventData($group), 'confirm_immediately' => true])
        ->call('validateDraft')
        ->assertForbidden();
});

it('shows details for advertisable events without requiring a description or persisting it', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::factory()->create();

    Livewire::actingAs($user)->test(Occasional::class)
        ->assertDontSee('Detalhes')
        ->set('advertisable', true)
        ->assertSee('Detalhes')
        ->assertSee('Informe uma descrição para a divulgação do evento.')
        ->set([...occasionalEventData($group), 'advertisable' => true, 'description' => '<p><br></p>'])
        ->call('validateDraft')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('place_reservations', 0);
});

it('maintains a buffer configuration for each selected place without creating reservations', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $community = eventCommunity();
    $nave      = $community->places()->create(['name' => 'Nave']);
    $hall      = $community->places()->create(['name' => 'Salão']);

    Livewire::actingAs($user)->test(Occasional::class)
        ->set('community_id', $community->id)
        ->set('place_ids', [$nave->id, $hall->id])
        ->assertSet('place_hours', [
            $nave->id => ['before' => 0, 'after' => 0],
            $hall->id => ['before' => 0, 'after' => 0],
        ])
        ->set("place_hours.{$nave->id}.before", 0.25)
        ->set("place_hours.{$hall->id}.after", 1.5)
        ->assertSet("place_hours.{$nave->id}.before", 0.25)
        ->assertSet("place_hours.{$hall->id}.after", 1.5)
        ->set('place_ids', [$hall->id])
        ->assertSet('place_hours', [$hall->id => ['before' => 0, 'after' => 1.5]]);

    $this->assertDatabaseCount('place_reservations', 0);
});

it('validates the event interval and individual place buffers without persistence', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::factory()->create();
    $place = eventCommunity()->places()->create(['name' => 'Nave']);

    Livewire::actingAs($user)->test(Occasional::class)
        ->set([...occasionalEventData($group), 'ends_at' => '2026-10-10T09:00'])
        ->set('community_id', $place->community_id)
        ->set('place_ids', [$place->id])
        ->set("place_hours.{$place->id}.before", -0.25)
        ->call('validateDraft')
        ->assertHasErrors(['ends_at', "place_hours.{$place->id}.before"]);

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('place_reservations', 0);
});

it('filters places by community and clears the selection when the community changes', function () {
    $user       = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $community  = eventCommunity();
    $other      = eventCommunity();
    $parent     = $community->places()->create(['name' => 'Centro Catequético']);
    $child      = $community->places()->create(['name' => 'Sala 2', 'main_place_id' => $parent->id]);
    $otherPlace = $other->places()->create(['name' => 'Salão']);

    Livewire::actingAs($user)->test(Occasional::class)
        ->set('community_id', $community->id)
        ->set('place_ids', [$child->id, $otherPlace->id])
        ->assertSet('place_ids', [$child->id])
        ->assertSee('Centro Catequético: Sala 2')
        ->set("place_hours.{$child->id}.before", 0.25)
        ->set('community_id', $other->id)
        ->assertSet('place_ids', [])
        ->assertSet('place_hours', [])
        ->set('place_ids', [$otherPlace->id])
        ->assertSet('place_ids', [$otherPlace->id])
        ->assertSee('Salão');
});
