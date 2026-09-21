<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\Gate;
use App\Livewire\Events\Forms\Occasional;

function occasionalEventData(Group $group): array
{
    $community = eventCommunity();
    $place     = $community->places()->create(['name' => fake()->unique()->word()]);

    return [
        'group_id'            => $group->id,
        'name'                => 'Encontro de formação',
        'type'                => EventTypeEnum::COURSE->value,
        'starts_at'           => '2026-10-10T09:00',
        'ends_at'             => '2026-10-10T11:00',
        'is_public'           => true,
        'advertisable'        => false,
        'confirm_immediately' => false,
        'community_id'        => $community->id,
        'place_ids'           => [$place->id],
        'place_hours'         => [
            $place->id => ['before' => 0, 'after' => 0],
        ],
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

it('allows a member exclusive user to select only their groups', function () {
    $user      = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $allowed   = Group::factory()->create(['name' => 'Grupo vinculado']);
    $unrelated = Group::factory()->create(['name' => 'Grupo não vinculado']);
    $user->groups()->attach($allowed);

    Livewire::actingAs($user)->test(Occasional::class)
        ->set(occasionalEventData($unrelated))
        ->call('validateDraft')
        ->assertForbidden();

    Livewire::actingAs($user)->test(Occasional::class)
        ->set(occasionalEventData($allowed))
        ->call('validateDraft')
        ->assertOk()
        ->assertHasNoErrors();
});

it('groups event organizers by community with ungrouped options first', function () {
    $user           = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $community      = eventCommunity();
    $ungrouped      = Group::factory()->create(['community_id' => null, 'name' => 'Grupo sem comunidade']);
    $communityGroup = Group::factory()->create(['community_id' => $community->id, 'name' => 'Grupo da comunidade']);
    $user->groups()->attach([$ungrouped->id, $communityGroup->id]);

    $component = Livewire::actingAs($user)->test(Occasional::class);

    expect($component->viewData('groups')->all())->toBe([
        [
            'label' => 'Sem comunidade',
            'value' => [['label' => 'Grupo sem comunidade', 'value' => $ungrouped->id]],
        ],
        [
            'label' => $community->name,
            'value' => [['label' => 'Grupo da comunidade', 'value' => $communityGroup->id]],
        ],
    ]);
    expect($component->viewData('isMemberOnly'))->toBeTrue();
});

it('allows a user with another role to select any group', function () {
    $user  = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);
    $group = Group::factory()->create(['name' => 'Grupo disponível']);

    $component = Livewire::actingAs($user)->test(Occasional::class);

    expect($component->viewData('isMemberOnly'))->toBeFalse();

    $component
        ->set(occasionalEventData($group))
        ->call('validateDraft')
        ->assertOk()
        ->assertHasNoErrors();
});

it('does not expose immediate confirmation and discards it for unauthorized users', function () {
    $user  = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);
    $group = Group::factory()->create();

    Livewire::actingAs($user)->test(Occasional::class)
        ->assertDontSee('Confirmar imediatamente')
        ->set([...occasionalEventData($group), 'confirm_immediately' => true])
        ->assertSet('confirm_immediately', false)
        ->call('validateDraft')
        ->assertOk()
        ->assertHasNoErrors();
});

it('shows optional details for advertisable events without requiring a description or persisting it', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::factory()->create();

    Livewire::actingAs($user)->test(Occasional::class)
        ->assertDontSee('Detalhes do evento')
        ->set('advertisable', true)
        ->assertSee('Detalhes do evento')
        ->assertSee('Não é obrigatório preencher estes campos neste momento')
        ->assertSee('Informe uma descrição para a divulgação do evento.')
        ->assertDontSee('Link de inscrição')
        ->assertDontSee('Prazo de inscrição')
        ->set('registration_required', true)
        ->assertSee('Link de inscrição')
        ->assertSee('Prazo de inscrição')
        ->set([...occasionalEventData($group), 'advertisable' => true, 'description' => '<p><br></p>'])
        ->call('validateDraft')
        ->assertHasNoErrors();

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('place_reservations', 0);
});

it('orders communities by their ID in the environment selector', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $first = Community::create([
        'name'         => 'Comunidade Zeta',
        'alias'        => 'comunidade-zeta',
        'abbreviation' => 'ZET',
    ]);
    $second = Community::create([
        'name'         => 'Comunidade Alfa',
        'alias'        => 'comunidade-alfa',
        'abbreviation' => 'ALF',
    ]);

    $component = Livewire::actingAs($user)->test(Occasional::class);

    expect($component->viewData('communities')->all())->toBe([
        ['label' => $first->name, 'value' => $first->id],
        ['label' => $second->name, 'value' => $second->id],
    ]);
});

it('orders spaces by their displayed names with rooms after their main space', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $community = eventCommunity();
    $zebra     = $community->places()->create(['name' => 'Zebra']);
    $alpha     = $community->places()->create(['name' => 'Alfa']);
    $center    = $community->places()->create(['name' => 'Centro Catequético']);
    $nave      = $community->places()->create(['name' => 'Nave']);
    $roomTen   = $community->places()->create(['name' => 'Sala 10', 'main_place_id' => $center->id]);
    $roomTwo   = $community->places()->create(['name' => 'Sala 2', 'main_place_id' => $center->id]);
    $roomOne   = $community->places()->create(['name' => 'Sala 1', 'main_place_id' => $center->id]);

    $component = Livewire::actingAs($user)->test(Occasional::class)
        ->set('community_id', $community->id);

    expect($component->viewData('places')->all())->toBe([
        ['label' => $alpha->name, 'value' => $alpha->id],
        ['label' => 'Centro Catequético', 'value' => $center->id],
        ['label' => 'Centro Catequético: Sala 1', 'value' => $roomOne->id],
        ['label' => 'Centro Catequético: Sala 2', 'value' => $roomTwo->id],
        ['label' => 'Centro Catequético: Sala 10', 'value' => $roomTen->id],
        ['label' => 'Nave', 'value' => $nave->id],
        ['label' => $zebra->name, 'value' => $zebra->id],
    ]);
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

it('requires at least one place before validating availability', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::factory()->create();

    Livewire::actingAs($user)->test(Occasional::class)
        ->set(occasionalEventData($group))
        ->set('place_ids', [])
        ->call('validateDraft')
        ->assertHasErrors(['place_ids'])
        ->assertSet('draftValidated', false)
        ->assertSet('placeConflicts', []);

    $this->assertDatabaseCount('events', 0);
    $this->assertDatabaseCount('place_reservations', 0);
});

it('persists a validated occasional event and redirects to its page', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::factory()->create();

    $component = Livewire::actingAs($user)->test(Occasional::class)
        ->set(occasionalEventData($group))
        ->call('validateDraft')
        ->assertHasNoErrors()
        ->assertSet('draftValidated', true)
        ->assertSee('Salvar evento')
        ->call('save');

    $event = Event::query()->sole();

    $component->assertRedirect(route('events.show', $event));
    expect(session('ts-ui:toast'))->toMatchArray([
        'type'        => 'success',
        'title'       => 'Evento cadastrado',
        'description' => 'O evento foi cadastrado com sucesso.',
    ])->and($event->status)->toBe(EventStatusEnum::PENDING)
        ->and($event->detail)->not->toBeNull()
        ->and($event->reservations)->toHaveCount(1)
        ->and($event->reservations->sole()->is_primary)->toBeTrue()
        ->and($event->logs->sole()->action)->toBe(EventLogActionEnum::CREATED);
});

it('reopens the conflict slide without persisting when final availability changes', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $group = Group::factory()->create();
    $data  = occasionalEventData($group);

    $component = Livewire::actingAs($user)->test(Occasional::class)
        ->set($data)
        ->call('validateDraft')
        ->assertSet('draftValidated', true);

    $existingEvent = Event::factory()->create([
        'starts_at' => '2026-10-10 09:00:00',
        'ends_at'   => '2026-10-10 11:00:00',
    ]);
    PlaceReservation::create([
        'event_id'      => $existingEvent->id,
        'place_id'      => $data['place_ids'][0],
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 11:00:00',
        'is_primary'    => true,
    ]);
    $eventCount = Event::count();

    $component->call('save')
        ->assertSet('conflictsSlide', true)
        ->assertSet('draftValidated', false);

    expect($component->get('placeConflicts'))->toHaveCount(1)
        ->and(Event::count())->toBe($eventCount);
    $this->assertDatabaseCount('event_details', 0);
    $this->assertDatabaseCount('event_logs', 0);
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
