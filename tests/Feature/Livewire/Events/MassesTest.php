<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\User;
use App\Models\Event;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Models\MassSchedule;
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

it('shows visible scheduled masses with a motivation search input', function () {
    $this->freezeTime();
    $user = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    Mass::factory()->count(2)->create(['motivation' => 'Missa Dominical']);
    Mass::factory()->create(['motivation' => 'Missa da Catequese', 'canceled_at' => now()]);
    Event::factory()->create(['name' => 'Reunião', 'type' => EventTypeEnum::MEETING]);
    Mass::factory()->create(['motivation' => 'Missa antiga', 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('masses', fn ($rows) => $rows->total() === 2)
        ->assertDontSee('Missa da Catequese')->assertDontSee('Missa antiga');
});

it('shows the mass community without depending on its primary reservation or lazy loading', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $mass      = Mass::factory()->create(['community_id' => $community->id, 'motivation' => 'Missa Dominical']);
    $mass->reservations()->delete();

    foreach ([['Capela vizinha', true], ['Capela secundária', false]] as [$name, $primary]) {
        $placeCommunity = Community::create(['name' => $name, 'alias' => $primary ? 'capela-vizinha' : 'capela-secundaria', 'abbreviation' => $primary ? 'CV' : 'CS']);
        $place          = $placeCommunity->places()->create(['name' => 'Espaço reservado']);
        $mass->reservations()->create(['place_id' => $place->id, 'is_primary' => $primary, 'reserved_from' => $mass->starts_at, 'reserved_to' => $mass->ends_at]);
    }
    $withoutReservations = Mass::factory()->create(['community_id' => $community->id]);
    $withoutReservations->reservations()->delete();
    Model::preventLazyLoading();

    try {
        $component = Livewire::actingAs($user)->test(Masses::class)
            ->assertSee('Missa Dominical')->assertSee($mass->starts_at->format('d/m/Y'))->assertSee($mass->starts_at->format('H:i'))
            ->assertSee('Matriz')->assertDontSee('Comunidade não informada')
            ->assertDontSee('Espaço reservado')
            ->assertDontSee('Grupo organizador')->assertDontSee('Tipo')->assertDontSee('Status')
            ->assertDontSee('Pendente')->assertDontSee('Local principal')->assertDontSee('Término')
            ->assertDontSee('filtersOpen')->assertDontSee('Missa/celebração');

        $document = new DOMDocument;
        @$document->loadHTML(mb_convert_encoding($component->html(), 'HTML-ENTITIES', 'UTF-8'));
        $tableText = (new DOMXPath($document))->evaluate('string(//tbody)');
        expect($tableText)->toContain('Matriz')->not->toContain('Capela vizinha')->not->toContain('Capela secundária');
    } finally {
        Model::preventLazyLoading(false);
    }
});

it('orders masses from today chronologically and optionally includes past dates', function () {
    $this->travelTo(now()->setTime(12, 0));
    $user    = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $future  = Mass::factory()->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    $ongoing = Mass::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    $ended   = Mass::factory()->create(['starts_at' => now()->subHours(2), 'ends_at' => now()]);
    $past    = Mass::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHour()]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('masses', fn ($rows) => $rows->modelKeys() === [$ended->id, $ongoing->id, $future->id])
        ->set('showPast', true)
        ->assertViewHas('masses', fn ($rows) => $rows->modelKeys() === [$past->id, $ended->id, $ongoing->id, $future->id]);
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

it('binds motivation safely and allows partial matches', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Mass::factory()->create(['motivation' => 'Missa Dominical']);

    Livewire::actingAs($user)->withQueryParams(['motivation' => "' OR 1=1 --"])->test(Masses::class)
        ->assertViewHas('masses', fn ($rows) => $rows->isEmpty())
        ->set('motivation', 'Dominic')->assertViewHas('masses', fn ($rows) => $rows->total() === 1);
});

it('paginates masses and resets the page for either filter', function (string $filter, string $value) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Mass::factory()->count(11)->create(['motivation' => 'Missa Dominical']);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertViewHas('masses', fn ($rows) => $rows->count() === 10 && $rows->total() === 11)
        ->call('setPage', 2)->assertViewHas('masses', fn ($rows) => $rows->count() === 1)
        ->set($filter, $value)->assertSet('paginators.page', 1);
})->with([['weekday', '0'], ['motivation', 'Missa Dominical']]);

it('filters through the mass community while preserving visibility', function (array $roles, bool $canSeeCanceled) {
    $user           = User::factory()->create(['roles' => $roles, 'is_active' => true]);
    $community      = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $otherCommunity = Community::create(['name' => 'Capela', 'alias' => 'capela', 'abbreviation' => 'CP']);
    $place          = $community->places()->create(['name' => 'Igreja matriz']);
    $otherPlace     = $otherCommunity->places()->create(['name' => 'Igreja da capela']);
    $confirmed      = Mass::factory()->create(['community_id' => $community->id]);
    $canceled       = Mass::factory()->create(['community_id' => $community->id, 'canceled_at' => now()]);
    $elsewhere      = Mass::factory()->create(['community_id' => $otherCommunity->id]);
    $unreserved     = Mass::factory()->create(['community_id' => $community->id]);
    $unreserved->reservations()->delete();

    foreach ([$confirmed, $canceled, $elsewhere] as $mass) {
        $mass->reservations()->delete();
    }

    foreach ([$confirmed, $canceled] as $mass) {
        $mass->reservations()->create(['place_id' => $place->id, 'is_primary' => true, 'reserved_from' => $mass->starts_at, 'reserved_to' => $mass->ends_at]);
    }
    $elsewhere->reservations()->create(['place_id' => $otherPlace->id, 'is_primary' => true, 'reserved_from' => $elsewhere->starts_at, 'reserved_to' => $elsewhere->ends_at]);
    $elsewhere->reservations()->create(['place_id' => $place->id, 'is_primary' => false, 'reserved_from' => $elsewhere->starts_at, 'reserved_to' => $elsewhere->ends_at]);
    $expectedIds = $canSeeCanceled ? [$confirmed->id, $canceled->id, $unreserved->id] : [$confirmed->id, $unreserved->id];

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

it('filters an inclusive date range and resets pagination when dates change', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $user   = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $masses = collect(['2026-09-19 08:00', '2026-09-20 00:00', '2026-09-21 23:59', '2026-09-22 08:00'])
        ->map(fn (string $date) => Mass::factory()->create(['starts_at' => $date, 'ends_at' => Carbon\Carbon::parse($date)->addHour()]));

    Livewire::actingAs($user)->test(Masses::class)
        ->call('setPage', 2)
        ->set('dateRange', ['2026-09-20', '2026-09-21'])
        ->assertSet('paginators.page', 1)
        ->assertViewHas('masses', fn ($rows) => $rows->modelKeys() === [$masses[1]->id, $masses[2]->id])
        ->set('showPast', true)
        ->set('dateRange', ['2026-09-19', '2026-09-19'])
        ->assertViewHas('masses', fn ($rows) => $rows->modelKeys() === [$masses[0]->id])
        ->set('dateRange', null)
        ->assertViewHas('masses', fn ($rows) => $rows->total() === 4);
});

it('ignores incomplete or malformed date ranges', function (mixed $range) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    Mass::factory()->create();

    Livewire::actingAs($user)->test(Masses::class)->set('dateRange', $range)
        ->assertViewHas('masses', fn ($rows) => $rows->total() === 1);
})->with([[['2026-09-20']], [['invalid', '2026-09-21']], [['2026-02-30', '2026-09-21']], [null]]);

it('groups active nonexpired schedules by weekday and community in chronological order', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $user           = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $late           = MassSchedule::factory()->create(['weekday' => 0, 'starts_at' => '18:00:00', 'motivation' => 'Celebração vespertina']);
    $early          = MassSchedule::factory()->create(['community_id' => $late->community_id, 'weekday' => 0, 'starts_at' => '08:00:00', 'valid_from' => today(), 'valid_until' => today()]);
    $otherCommunity = Community::create(['name' => 'Capela semanal', 'alias' => 'capela-semanal', 'abbreviation' => 'CS']);
    $monday         = MassSchedule::factory()->create(['community_id' => $otherCommunity->id, 'weekday' => 1, 'starts_at' => '07:00:00']);
    MassSchedule::factory()->create(['is_active' => false, 'motivation' => 'Horário inativo']);
    MassSchedule::factory()->create(['valid_until' => today()->subDay(), 'motivation' => 'Horário expirado']);
    $futureCommunity = Community::create(['name' => 'Comunidade futura', 'alias' => 'comunidade-futura', 'abbreviation' => 'CF']);
    $future          = MassSchedule::factory()->create(['community_id' => $futureCommunity->id, 'weekday' => 1, 'starts_at' => '09:00:00', 'valid_from' => today()->addDay(), 'motivation' => 'Horário futuro']);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertSee('Missas programadas')->assertSee('Cronograma semanal')
        ->assertSee('Por dia da semana')->assertSee('Por comunidade')
        ->assertSee('Celebração vespertina')->assertSee('18:00')
        ->assertDontSee('Horário inativo')->assertDontSee('Horário expirado')->assertSee('Horário futuro')
        ->assertViewHas('schedulesByWeekday', fn ($groups) => $groups->keys()->all() === [0, 1]
            && $groups[0]->modelKeys() === [$early->id, $late->id]
            && $groups[1]->modelKeys() === [$monday->id, $future->id])
        ->assertViewHas('schedulesByCommunity', fn ($groups) => $groups->count() === 3
            && $groups[$late->community_id]->modelKeys() === [$early->id, $late->id]);
});

it('keeps the date picker empty and partial states stable across renders', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->test(Masses::class)
        ->assertSet('dateRange', null)
        ->call('$refresh')->assertSet('dateRange', null)
        ->set('dateRange', ['2026-09-20', null])
        ->assertSet('dateRange', ['2026-09-20', null])
        ->call('$refresh')->assertSet('dateRange', ['2026-09-20', null])
        ->set('dateRange', ['2026-09-20', '2026-09-22'])
        ->assertSet('dateRange', ['2026-09-20', '2026-09-22'])
        ->set('dateRange', null)
        ->call('$refresh')->assertSet('dateRange', null)
        ->set('dateRange', [null, null])
        ->assertSet('dateRange', null);
});
