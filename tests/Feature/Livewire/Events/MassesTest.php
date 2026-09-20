<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\User;
use App\Models\Event;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use Illuminate\Database\Eloquent\Model;
use App\Livewire\Masses\Index as Masses;

it('filters each weekday in the database and combines it with motivation', function (string $weekday) {
    $this->travelTo(now()->setDate(2026, 9, 19)->startOfDay());
    $user   = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $masses = collect(range(0, 6))->map(fn (int $day) => Mass::factory()->create([
        'motivation' => 'Missa Dominical',
        'starts_at'  => now()->addDays($day + 1)->setTime(8, 0),
        'ends_at'    => now()->addDays($day + 1)->setTime(9, 0),
    ]));
    Mass::factory()->create([
        'motivation' => 'Missa com Novena',
        'starts_at'  => $masses[(int) $weekday]->starts_at,
        'ends_at'    => $masses[(int) $weekday]->ends_at,
    ]);

    Livewire::actingAs($user)->withQueryParams(['weekday' => $weekday, 'motivation' => 'Missa Dominical'])
        ->test(Masses::class)
        ->assertViewHas('masses', function ($rows) use ($masses, $weekday) {
            expect($rows->modelKeys())->toBe([$masses[(int) $weekday]->id]);

            return true;
        });
})->with(['0', '1', '2', '3', '4', '5', '6']);

it('offers distinct motivations only from visible upcoming masses', function () {
    $this->freezeTime();
    $user = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    Mass::factory()->count(2)->create(['motivation' => 'Missa Dominical']);
    Mass::factory()->create(['motivation' => 'Missa da Catequese', 'canceled_at' => now()]);
    Event::factory()->create(['name' => 'Reunião', 'type' => EventTypeEnum::MEETING]);
    Mass::factory()->create(['motivation' => 'Missa antiga', 'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('motivations', fn ($values) => $values->all() === ['Missa Dominical'])
        ->assertDontSee('Missa da Catequese')->assertDontSee('Missa antiga');
});

it('shows only motivation start time and the primary reservation community without lazy loading', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $mass = Mass::factory()->create(['motivation' => 'Missa Dominical']);

    foreach ([['Matriz', true], ['Capela secundária', false]] as [$name, $primary]) {
        $community = Community::create(['name' => $name, 'alias' => $primary ? 'matriz' : 'capela', 'abbreviation' => $primary ? 'MT' : 'CP']);
        $place     = $community->places()->create(['name' => 'Espaço reservado']);
        $mass->reservations()->create(['place_id' => $place->id, 'is_primary' => $primary, 'reserved_from' => $mass->starts_at, 'reserved_to' => $mass->ends_at]);
    }
    Mass::factory()->create();
    Model::preventLazyLoading();

    try {
        $component = Livewire::actingAs($user)->test(Masses::class)
            ->assertSee('Missa Dominical')->assertSee($mass->starts_at->format('d/m/Y H:i'))
            ->assertSee('Matriz')->assertSee('Comunidade não informada')
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
    $future  = Mass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $ongoing = Mass::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    Mass::factory()->create(['starts_at' => now()->subHours(2), 'ends_at' => now()]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('masses', fn ($rows) => $rows->modelKeys() === [$ongoing->id, $future->id]);
});

it('normalizes malformed filters and keeps user input bound as values', function (mixed $weekday, mixed $motivation, string $expectedMotivation) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Mass::factory()->create();

    Livewire::actingAs($user)->withQueryParams(['weekday' => $weekday, 'motivation' => $motivation])
        ->test(Masses::class)->assertSet('weekday', '')->assertSet('motivation', $expectedMotivation);
})->with([
    ['7', [], ''],
    [['0'], ['nested'], ''],
    ['0 OR 1=1', str_repeat('a', 300), str_repeat('a', 255)],
]);

it('does not interpret motivation as SQL or a partial match', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Mass::factory()->create(['motivation' => 'Missa Dominical']);

    Livewire::actingAs($user)->withQueryParams(['motivation' => "' OR 1=1 --"])->test(Masses::class)
        ->assertViewHas('masses', fn ($rows) => $rows->isEmpty())
        ->set('motivation', 'Missa')->assertViewHas('masses', fn ($rows) => $rows->isEmpty());
});

it('paginates masses and resets the page for either filter', function (string $filter, string $value) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Mass::factory()->count(11)->create(['motivation' => 'Missa Dominical']);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('masses', fn ($rows) => $rows->count() === 10 && $rows->total() === 11)
        ->call('setPage', 2)->assertViewHas('masses', fn ($rows) => $rows->count() === 1)
        ->set($filter, $value)->assertSet('paginators.page', 1);
})->with([['weekday', '0'], ['motivation', 'Missa Dominical']]);

it('filters through the primary reservation community while preserving visibility', function (array $roles, bool $canSeeCanceled) {
    $user           = User::factory()->create(['roles' => $roles, 'is_active' => true]);
    $community      = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $otherCommunity = Community::create(['name' => 'Capela', 'alias' => 'capela', 'abbreviation' => 'CP']);
    $place          = $community->places()->create(['name' => 'Igreja matriz']);
    $otherPlace     = $otherCommunity->places()->create(['name' => 'Igreja da capela']);
    $confirmed      = Mass::factory()->create();
    $canceled       = Mass::factory()->create(['canceled_at' => now()]);
    $elsewhere      = Mass::factory()->create();
    Mass::factory()->create();

    foreach ([$confirmed, $canceled] as $mass) {
        $mass->reservations()->create(['place_id' => $place->id, 'is_primary' => true, 'reserved_from' => $mass->starts_at, 'reserved_to' => $mass->ends_at]);
    }
    $elsewhere->reservations()->create(['place_id' => $otherPlace->id, 'is_primary' => true, 'reserved_from' => $elsewhere->starts_at, 'reserved_to' => $elsewhere->ends_at]);
    $elsewhere->reservations()->create(['place_id' => $place->id, 'is_primary' => false, 'reserved_from' => $elsewhere->starts_at, 'reserved_to' => $elsewhere->ends_at]);
    $expectedIds = $canSeeCanceled ? [$confirmed->id, $canceled->id] : [$confirmed->id];

    Livewire::actingAs($user)->withQueryParams(['community_id' => (string) $community->id])->test(Masses::class)
        ->assertSet('community_id', $community->id)
        ->assertViewHas('masses', fn ($rows) => $rows->pluck('id')->sort()->values()->all() === $expectedIds)
        ->set('community_id', '0')
        ->assertViewHas('masses', fn ($rows) => $rows->total() === ($canSeeCanceled ? 4 : 3));
})->with([[['member'], false], [['admin'], true], [['member', 'pascom'], true]]);

it('normalizes malformed community parameters', function (mixed $invalid) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->withQueryParams(['community_id' => $invalid])->test(Masses::class)
        ->assertSet('community_id', 0);
})->with(['', 'invalid', '-1', '1.5', [['id' => 1]]]);

it('returns no masses for an unknown community and resets pagination when community changes', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Mass::factory()->count(11)->create();

    Livewire::actingAs($user)->test(Masses::class)->call('setPage', 2)
        ->set('community_id', '999999')->assertSet('paginators.page', 1)
        ->assertViewHas('masses', fn ($rows) => $rows->isEmpty());
});
