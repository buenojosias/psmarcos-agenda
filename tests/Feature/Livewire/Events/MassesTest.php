<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use Illuminate\Database\Eloquent\Model;
use App\Livewire\Masses\Index as Masses;

it('filters each weekday in the database and combines it with motivation', function (string $weekday) {
    $this->travelTo(now()->setDate(2026, 9, 19)->startOfDay());
    $user   = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $events = collect(range(0, 6))->map(fn (int $day) => Event::factory()->create([
        'name'      => 'Missa Dominical', 'type' => EventTypeEnum::MASS, 'group_id' => null,
        'starts_at' => now()->addDays($day + 1)->setTime(8, 0),
        'ends_at'   => now()->addDays($day + 1)->setTime(9, 0),
    ]));
    Event::factory()->create([
        'name'      => 'Missa com Novena', 'type' => EventTypeEnum::MASS, 'group_id' => null,
        'starts_at' => $events[(int) $weekday]->starts_at,
        'ends_at'   => $events[(int) $weekday]->ends_at,
    ]);

    Livewire::actingAs($user)->withQueryParams(['weekday' => $weekday, 'motivation' => 'Missa Dominical'])
        ->test(Masses::class)
        ->assertViewHas('events', function ($rows) use ($events, $weekday) {
            expect($rows->modelKeys())->toBe([$events[(int) $weekday]->id]);

            return true;
        });
})->with(['0', '1', '2', '3', '4', '5', '6']);

it('offers distinct motivations only from visible upcoming masses', function () {
    $this->freezeTime();
    $user = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    Event::factory()->count(2)->create(['name' => 'Missa Dominical', 'type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::CONFIRMED]);
    Event::factory()->create(['name' => 'Missa da Catequese', 'type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::PENDING]);
    Event::factory()->create(['name' => 'Reunião', 'type' => EventTypeEnum::MEETING, 'status' => EventStatusEnum::CONFIRMED]);
    Event::factory()->create(['name' => 'Missa antiga', 'type' => EventTypeEnum::MASS, 'status' => EventStatusEnum::CONFIRMED, 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('motivations', fn ($values) => $values->all() === ['Missa Dominical'])
        ->assertDontSee('Missa da Catequese')->assertDontSee('Missa antiga');
});

it('shows only motivation start time and the primary reservation community without lazy loading', function () {
    $user  = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $event = Event::factory()->create(['name' => 'Missa Dominical', 'type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::PENDING]);

    foreach ([['Matriz', true], ['Capela secundária', false]] as [$name, $primary]) {
        $community = Community::create(['name' => $name, 'alias' => $primary ? 'matriz' : 'capela', 'abbreviation' => $primary ? 'MT' : 'CP']);
        $place     = $community->places()->create(['name' => 'Espaço reservado']);
        $event->reservations()->create(['place_id' => $place->id, 'is_primary' => $primary, 'reserved_from' => $event->starts_at, 'reserved_to' => $event->ends_at]);
    }
    Event::factory()->create(['type' => EventTypeEnum::MASS, 'group_id' => null]);
    Model::preventLazyLoading();

    try {
        $component = Livewire::actingAs($user)->test(Masses::class)
            ->assertSee('Missa Dominical')->assertSee($event->starts_at->format('d/m/Y H:i'))
            ->assertSee('Matriz')->assertSee('Comunidade não informada')
            ->assertSee(route('events.show', $event))
            ->assertDontSee('Espaço reservado')
            ->assertDontSee('Grupo organizador')->assertDontSee('Tipo')->assertDontSee('Status')
            ->assertDontSee('Pendente')->assertDontSee('Local principal')->assertDontSee('Término')
            ->assertDontSee('filtersOpen')->assertDontSee('Missa/celebração');

        $document = new DOMDocument;
        @$document->loadHTML(mb_convert_encoding($component->html(), 'HTML-ENTITIES', 'UTF-8'));
        $tableText = (new DOMXPath($document))->evaluate('string(//tbody)');
        expect($tableText)->toContain('Matriz')->not->toContain('Capela secundária');
    } finally {
        Model::preventLazyLoading(false);
    }
});

it('orders upcoming and ongoing masses chronologically and excludes ended masses', function () {
    $this->freezeTime();
    $user    = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $future  = Event::factory()->create(['type' => EventTypeEnum::MASS, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $ongoing = Event::factory()->create(['type' => EventTypeEnum::MASS, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    Event::factory()->create(['type' => EventTypeEnum::MASS, 'starts_at' => now()->subHours(2), 'ends_at' => now()]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('events', fn ($rows) => $rows->modelKeys() === [$ongoing->id, $future->id]);
});

it('normalizes malformed filters and keeps user input bound as values', function (mixed $weekday, mixed $motivation, string $expectedMotivation) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Event::factory()->create(['type' => EventTypeEnum::MASS]);

    Livewire::actingAs($user)->withQueryParams(['weekday' => $weekday, 'motivation' => $motivation])
        ->test(Masses::class)->assertSet('weekday', '')->assertSet('motivation', $expectedMotivation);
})->with([
    ['7', [], ''],
    [['0'], ['nested'], ''],
    ['0 OR 1=1', str_repeat('a', 300), str_repeat('a', 255)],
]);

it('does not interpret motivation as SQL or a partial match', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Event::factory()->create(['name' => 'Missa Dominical', 'type' => EventTypeEnum::MASS]);

    Livewire::actingAs($user)->withQueryParams(['motivation' => "' OR 1=1 --"])->test(Masses::class)
        ->assertViewHas('events', fn ($rows) => $rows->isEmpty())
        ->set('motivation', 'Missa')->assertViewHas('events', fn ($rows) => $rows->isEmpty());
});

it('paginates masses and resets the page for either filter', function (string $filter, string $value) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Event::factory()->count(11)->create(['name' => 'Missa Dominical', 'type' => EventTypeEnum::MASS]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('events', fn ($rows) => $rows->count() === 10 && $rows->total() === 11)
        ->call('setPage', 2)->assertViewHas('events', fn ($rows) => $rows->count() === 1)
        ->set($filter, $value)->assertSet('paginators.page', 1);
})->with([['weekday', '0'], ['motivation', 'Missa Dominical']]);

it('filters through the primary reservation community while preserving visibility', function (array $roles, bool $canSeePending) {
    $user           = User::factory()->create(['roles' => $roles, 'is_active' => true]);
    $community      = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $otherCommunity = Community::create(['name' => 'Capela', 'alias' => 'capela', 'abbreviation' => 'CP']);
    $place          = $community->places()->create(['name' => 'Igreja matriz']);
    $otherPlace     = $otherCommunity->places()->create(['name' => 'Igreja da capela']);
    $confirmed      = Event::factory()->create(['type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::CONFIRMED]);
    $pending        = Event::factory()->create(['type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::PENDING]);
    $elsewhere      = Event::factory()->create(['type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::CONFIRMED]);
    Event::factory()->create(['type' => EventTypeEnum::MASS, 'group_id' => null, 'status' => EventStatusEnum::CONFIRMED]);

    foreach ([$confirmed, $pending] as $event) {
        $event->reservations()->create(['place_id' => $place->id, 'is_primary' => true, 'reserved_from' => $event->starts_at, 'reserved_to' => $event->ends_at]);
    }
    $elsewhere->reservations()->create(['place_id' => $otherPlace->id, 'is_primary' => true, 'reserved_from' => $elsewhere->starts_at, 'reserved_to' => $elsewhere->ends_at]);
    $elsewhere->reservations()->create(['place_id' => $place->id, 'is_primary' => false, 'reserved_from' => $elsewhere->starts_at, 'reserved_to' => $elsewhere->ends_at]);
    $expectedIds = $canSeePending ? [$confirmed->id, $pending->id] : [$confirmed->id];

    Livewire::actingAs($user)->withQueryParams(['community_id' => (string) $community->id])->test(Masses::class)
        ->assertSet('community_id', $community->id)
        ->assertViewHas('events', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === $expectedIds)
        ->set('community_id', '0')
        ->assertViewHas('events', fn ($rows) => $rows->total() === ($canSeePending ? 4 : 3));
})->with([[['member'], false], [['admin'], true], [['member', 'pascom'], true]]);

it('normalizes malformed community parameters', function (mixed $invalid) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->withQueryParams(['community_id' => $invalid])->test(Masses::class)
        ->assertSet('community_id', 0);
})->with(['', 'invalid', '-1', '1.5', [['id' => 1]]]);

it('returns no masses for an unknown community and resets pagination when community changes', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Event::factory()->count(11)->create(['type' => EventTypeEnum::MASS, 'group_id' => null]);

    Livewire::actingAs($user)->test(Masses::class)->call('setPage', 2)
        ->set('community_id', '999999')->assertSet('paginators.page', 1)
        ->assertViewHas('events', fn ($rows) => $rows->isEmpty());
});
