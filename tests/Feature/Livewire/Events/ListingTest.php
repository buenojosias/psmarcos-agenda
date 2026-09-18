<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use App\Livewire\Events\Index;
use App\Livewire\Masses\Index as Masses;

it('requires authentication and active accounts', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['is_active' => false]))->get(route($route))->assertForbidden();
})->with(['events.index', 'masses.index']);

it('separates events and masses through the authenticated routes', function () {
    $event = Event::factory()->create(['type' => EventTypeEnum::MEETING, 'name' => 'Reunião da pastoral']);
    $mass  = Event::factory()->create(['type' => EventTypeEnum::MASS, 'name' => 'Celebração dominical']);
    $this->actingAs(User::factory()->create(['is_active' => true, 'roles' => ['admin']]));

    $this->get(route('events.index'))->assertSee($event->name)->assertDontSeeText($mass->name);
    $this->get(route('masses.index'))->assertSee($mass->name)->assertDontSeeText($event->name);
});

it('applies member visibility before status and manually supplied review filters', function () {
    $user  = User::factory()->create(['is_active' => true, 'roles' => ['member']]);
    $group = Group::factory()->create();
    $group->users()->attach($user);
    $own   = Event::factory()->for($group)->create(['status' => EventStatusEnum::PENDING]);
    $other = Event::factory()->for(Group::factory())->create(['status' => EventStatusEnum::PENDING]);

    Livewire::actingAs($user)->withQueryParams(['status' => 'pending', 'scope' => 'review'])->test(Index::class)
        ->assertSet('scope', 'all')->assertSee($own->name)->assertDontSee($other->name)
        ->assertDontSee('Aguardando análise');
});

it('restricts mine to user groups for all profiles', function (array $roles) {
    $user  = User::factory()->create(['is_active' => true, 'roles' => $roles]);
    $group = Group::factory()->create();
    $group->users()->attach($user);
    $own       = Event::factory()->for($group)->create(['status' => EventStatusEnum::CONFIRMED]);
    $other     = Event::factory()->for(Group::factory())->create(['status' => EventStatusEnum::CONFIRMED]);
    $ungrouped = Event::factory()->create(['group_id' => null, 'status' => EventStatusEnum::CONFIRMED]);

    Livewire::actingAs($user)->withQueryParams(['scope' => 'mine'])->test(Index::class)
        ->assertSee($own->name)->assertDontSee($other->name)->assertDontSee($ungrouped->name);
})->with([[['member']], [['secretary']], [['member', 'pascom']]]);

it('shows only pending and rescheduled events awaiting review', function () {
    $events = collect(EventStatusEnum::cases())->map(fn ($status) => Event::factory()->create(['status' => $status]));

    Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => ['admin']]))
        ->withQueryParams(['scope' => 'review'])->test(Index::class)
        ->assertViewHas('events', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === $events->whereIn('status', [EventStatusEnum::PENDING, EventStatusEnum::RESCHEDULED])->pluck('id')->all());
});

it('shows members only confirmed masses and elevated users all masses', function (array $roles, int $count) {
    foreach (EventStatusEnum::cases() as $status) {
        Event::factory()->create(['group_id' => null, 'type' => EventTypeEnum::MASS, 'status' => $status]);
    }

    Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => $roles]))->test(Masses::class)
        ->assertViewHas('events', fn ($rows) => $rows->total() === $count);
})->with([[['member'], 1], [['admin'], 5], [['member', 'pascom'], 5]]);

it('renders status badges according to ownership and roles', function (array $roles, bool $own, bool $badge) {
    $user  = User::factory()->create(['is_active' => true, 'roles' => $roles]);
    $group = Group::factory()->create();

    if ($own) {
        $group->users()->attach($user);
    }
    Event::factory()->for($group)->create(['status' => EventStatusEnum::CONFIRMED]);

    $html     = Livewire::actingAs($user)->test(Index::class)->html();
    $document = new DOMDocument;
    @$document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $tableText = (new DOMXPath($document))->evaluate('string(//tbody)');
    expect(str_contains($tableText, 'Confirmado'))->toBe($badge);
})->with([
    [['member'], true, true],
    [['member'], false, false],
    [['admin'], false, true],
    [['member', 'pascom'], false, true],
]);

it('uses the end time for periods and orders each period correctly', function () {
    $this->travelTo(now()->setDate(2026, 9, 18)->setTime(12, 0));
    $ongoing = Event::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    $future  = Event::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $ended   = Event::factory()->create(['starts_at' => now()->subHours(2), 'ends_at' => now()]);
    $old     = Event::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

    Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => ['admin']]))->test(Index::class)
        ->assertViewHas('events', fn ($rows) => $rows->modelKeys() === [$ongoing->id, $future->id])
        ->set('period', 'past')
        ->assertViewHas('events', fn ($rows) => $rows->modelKeys() === [$ended->id, $old->id])
        ->set('period', 'all')->assertViewHas('events', fn ($rows) => $rows->total() === 4);
});

it('normalizes invalid URL filters including arrays', function (mixed $invalid) {
    Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => ['admin']]))
        ->withQueryParams(array_fill_keys(['status', 'type', 'period', 'scope', 'group'], $invalid))
        ->test(Index::class)->assertSet('status', '')->assertSet('type', '')->assertSet('period', 'upcoming')
        ->assertSet('scope', 'all')->assertSet('group', '');
})->with(['invalid', [['nested' => 'invalid']]]);

it('filters by name type and group and normalizes mass type on the general list', function () {
    $group = Group::factory()->create();
    $match = Event::factory()->for($group)->create(['name' => 'Encontro litúrgico', 'type' => EventTypeEnum::MEETING]);
    Event::factory()->for($group)->create(['name' => 'Encontro musical', 'type' => EventTypeEnum::REHEARSAL]);
    Event::factory()->for(Group::factory())->create(['name' => 'Encontro de jovens', 'type' => EventTypeEnum::MEETING]);

    Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => ['admin']]))
        ->withQueryParams(['search' => '  Encontro  ', 'type' => 'meeting', 'group' => (string) $group->id])
        ->test(Index::class)->assertSet('search', 'Encontro')
        ->assertViewHas('events', fn ($rows) => $rows->modelKeys() === [$match->id])
        ->set('type', 'mass')->assertSet('type', '')
        ->set('search', str_repeat('x', 110))->assertSet('search', str_repeat('x', 100));
});

it('paginates and resets the page when a filter changes', function () {
    Event::factory()->count(11)->create(['type' => EventTypeEnum::MEETING]);

    Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => ['admin']]))->test(Index::class)
        ->assertViewHas('events', fn ($rows) => $rows->count() === 10 && $rows->total() === 11)
        ->call('setPage', 2)->assertViewHas('events', fn ($rows) => $rows->count() === 1)
        ->set('status', 'confirmed')->assertSet('paginators.page', 1);
});

it('renders escaped names and the primary reservation without lazy loading', function () {
    $group     = Group::factory()->create(['name' => 'Pastoral organizadora']);
    $event     = Event::factory()->for($group)->create(['name' => '<script>alert(1)</script>']);
    $community = App\Models\Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão principal']);
    $event->reservations()->create([
        'place_id'      => $place->id, 'is_primary' => true,
        'reserved_from' => $event->starts_at, 'reserved_to' => $event->ends_at,
    ]);
    Event::factory()->for($group)->create();
    Illuminate\Database\Eloquent\Model::preventLazyLoading();

    try {
        Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => ['admin']]))->test(Index::class)
            ->assertSee('Salão principal')->assertSee('Pastoral organizadora')
            ->assertSee($event->name)->assertDontSee($event->name, false);
    } finally {
        Illuminate\Database\Eloquent\Model::preventLazyLoading(false);
    }
});

it('rechecks group membership on subsequent requests', function () {
    $user  = User::factory()->create(['is_active' => true, 'roles' => ['member']]);
    $group = Group::factory()->create();
    $group->users()->attach($user);
    $event     = Event::factory()->for($group)->create(['status' => EventStatusEnum::PENDING]);
    $component = Livewire::actingAs($user)->test(Index::class)->assertSee($event->name);
    $group->users()->detach($user);

    $component->call('$refresh')->assertDontSee($event->name)
        ->set('scope', 'mine')->assertViewHas('events', fn ($rows) => $rows->isEmpty());
});

it('filters masses by motivation without exposing pending masses to members', function (array $roles, int $count) {
    Event::factory()->create(['name' => 'Missa com Novena', 'type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::PENDING]);
    Event::factory()->create(['type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::CONFIRMED]);

    Livewire::actingAs(User::factory()->create(['is_active' => true, 'roles' => $roles]))
        ->withQueryParams(['motivation' => 'Missa com Novena'])->test(Masses::class)
        ->assertViewHas('events', fn ($rows) => $rows->total() === $count);
})->with([[['member'], 0], [['admin'], 1]]);
