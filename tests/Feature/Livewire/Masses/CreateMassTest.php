<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\User;
use App\Models\Event;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\UserRoleEnum;
use App\Models\MassSchedule;
use App\Livewire\Masses\Index;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Livewire\Masses\CreateSchedule;
use App\Actions\CreateMassScheduleAction;
use App\Livewire\Masses\CreateExtraordinary;
use App\Actions\CreateExtraordinaryMassAction;
use Illuminate\Auth\Access\AuthorizationException;

function massCreationCommunity(array $names = ['Nave', 'Sacristia', 'Estacionamento']): Community
{
    $community = Community::create(['name' => fake()->unique()->word(), 'alias' => fake()->unique()->slug(2), 'abbreviation' => fake()->unique()->lexify('???')]);
    $community->places()->createMany(array_map(fn (string $name): array => ['name' => $name], $names));

    return $community;
}

function extraordinaryMassData(Community $community): array
{
    return ['community_id' => $community->id, 'motivation' => 'Celebração especial', 'date' => '2026-09-22', 'starts_at' => '19:00', 'ends_at' => '20:00'];
}

function recurringMassData(Community $community): array
{
    return ['community_id' => $community->id, 'motivation' => 'Missa semanal', 'weekday' => 2, 'starts_at' => '19:00', 'duration_minutes' => 60, 'valid_from' => '2026-09-20', 'valid_until' => '2026-10-06'];
}

it('allows only active users with a permitted role to create masses', function (string $role, bool $active) {
    $user = User::factory()->make(['roles' => [$role], 'is_active' => $active]);

    expect(Gate::forUser($user)->allows('create', Mass::class))
        ->toBe($active && in_array($role, ['secretary', 'cpp', 'priest', 'admin'], true));
})->with(array_map(fn (UserRoleEnum $role): string => $role->value, UserRoleEnum::cases()))->with([true, false]);

it('allows a permitted role alongside other roles', function () {
    $user = User::factory()->make(['roles' => ['pascom', 'secretary'], 'is_active' => true]);

    expect(Gate::forUser($user)->allows('create', Mass::class))->toBeTrue();
});

it('protects both submit and confirmation on the backend', function (string $component, string $method) {
    $user = User::factory()->create(['roles' => ['member'], 'is_active' => true]);

    Livewire::actingAs($user)->test($component)->call($method)->assertForbidden();

    $this->assertDatabaseCount('masses', 0);
    $this->assertDatabaseCount('mass_schedules', 0);
})->with([CreateSchedule::class, CreateExtraordinary::class])->with(['save', 'confirm']);

it('protects actions called outside livewire', function (string $action) {
    $this->actingAs(User::factory()->create(['roles' => ['admin'], 'is_active' => false]));

    expect(fn () => app($action)->handle([], confirmed: true))->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('masses', 0);
})->with([CreateMassScheduleAction::class, CreateExtraordinaryMassAction::class]);

it('shows creation buttons only for permitted users', function (string $role, bool $visible) {
    $user      = User::factory()->create(['roles' => [$role], 'is_active' => true]);
    $component = Livewire::actingAs($user)->test(Index::class);

    if ($visible) {
        $component->assertSee('Cadastrar horário recorrente')->assertSee('Cadastrar missa extraordinária');
    } else {
        $component->assertDontSee('Cadastrar horário recorrente')->assertDontSee('Cadastrar missa extraordinária');
    }
})->with([['admin', true], ['member', false]]);

it('creates normalized inclusive recurring occurrences and their three community reservations', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $other     = massCreationCommunity();
    $community = massCreationCommunity();
    $user      = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);

    Livewire::actingAs($user)->test(CreateSchedule::class)->assertSet('duration_minutes', 60)
        ->set(recurringMassData($community))->call('save')->assertHasNoErrors()->assertDispatched('created');

    $schedule = MassSchedule::sole();
    expect($schedule->valid_from->toDateString())->toBe('2026-09-22');
    expect($schedule->masses()->orderBy('starts_at')->get()->map(fn (Mass $mass): string => $mass->starts_at->format('Y-m-d H:i'))->all())
        ->toBe(['2026-09-22 19:00', '2026-09-29 19:00', '2026-10-06 19:00']);
    $this->assertDatabaseCount('place_reservations', 9);
    expect(PlaceReservation::query()->whereIn('place_id', $other->places->modelKeys())->exists())->toBeFalse();
    $mass = $schedule->masses()->oldest('starts_at')->first();
    expect($mass->primaryReservation->place->name)->toBe('Nave');
    expect($mass->reservations->pluck('reserved_from')->map->format('H:i')->unique()->all())->toBe(['18:30']);
    expect($mass->reservations->pluck('reserved_to')->map->format('H:i')->unique()->all())->toBe(['20:30']);
});

it('allows recurring schedules without a motivation', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $user      = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);

    Livewire::actingAs($user)->test(CreateSchedule::class)
        ->set([...recurringMassData($community), 'motivation' => ''])
        ->call('save')->assertHasNoErrors()->assertDispatched('created');

    expect(MassSchedule::sole()->motivation)->toBeNull();
    expect(Mass::query()->whereNull('motivation')->exists())->toBeTrue();
});

it('does not duplicate a generated schedule occurrence or its reservations', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $schedule  = MassSchedule::factory()->create([...recurringMassData($community), 'valid_from' => '2026-09-22']);

    $first  = $schedule->generateMassForDate(today()->addDays(2));
    $second = $schedule->generateMassForDate(today()->addDays(2));

    expect($second->id)->toBe($first->id);
    $this->assertDatabaseCount('masses', 1);
    $this->assertDatabaseCount('place_reservations', 3);
});

it('creates an extraordinary mass without a schedule and with padded reservations', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $user      = User::factory()->create(['roles' => ['priest'], 'is_active' => true]);

    Livewire::actingAs($user)->test(CreateExtraordinary::class)
        ->set(extraordinaryMassData($community))->call('save')->assertHasNoErrors()->assertDispatched('created');

    $mass = Mass::sole();
    expect($mass->mass_schedule_id)->toBeNull();
    $this->assertDatabaseCount('mass_schedules', 0);
    $this->assertDatabaseCount('place_reservations', 3);
    $this->assertDatabaseHas('place_reservations', ['mass_id' => $mass->id, 'reserved_from' => '2026-09-22 18:30:00', 'reserved_to' => '2026-09-22 20:30:00', 'is_primary' => true]);
});

it('refuses retroactive dates and invalid intervals', function (string $component, string $field, mixed $value) {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $data      = $component === CreateSchedule::class ? recurringMassData($community) : extraordinaryMassData($community);
    $user      = User::factory()->create(['roles' => ['cpp'], 'is_active' => true]);

    Livewire::actingAs($user)->test($component)->set($data)->set($field, $value)->call('save')->assertHasErrors([$field]);

    $this->assertDatabaseCount('masses', 0);
    $this->assertDatabaseCount('mass_schedules', 0);
    $this->assertDatabaseCount('place_reservations', 0);
})->with([
    [CreateSchedule::class, 'valid_from', '2026-09-19'],
    [CreateExtraordinary::class, 'date', '2026-09-19'],
    [CreateSchedule::class, 'valid_until', '2026-09-19'],
    [CreateSchedule::class, 'valid_until', '2026-09-21'],
    [CreateSchedule::class, 'duration_minutes', 0],
    [CreateSchedule::class, 'weekday', 7],
    [CreateExtraordinary::class, 'ends_at', '19:00'],
    [CreateExtraordinary::class, 'ends_at', '18:00'],
]);

it('allows creation when any mandatory community space is missing', function (string $component, string $missing) {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    massCreationCommunity();
    $community = massCreationCommunity(array_values(array_diff(['Nave', 'Sacristia', 'Estacionamento'], [$missing])));
    $data      = $component === CreateSchedule::class ? recurringMassData($community) : extraordinaryMassData($community);
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->test($component)->set($data)->call('save')->assertHasNoErrors()->assertDispatched('created');

    $this->assertDatabaseCount('masses', $component === CreateSchedule::class ? 3 : 1);
    $this->assertDatabaseCount('mass_schedules', $component === CreateSchedule::class ? 1 : 0);
    $this->assertDatabaseCount('place_reservations', $component === CreateSchedule::class ? 6 : 2);
})->with([CreateSchedule::class, CreateExtraordinary::class])->with(['Nave', 'Sacristia', 'Estacionamento']);

it('requires explicit confirmation of event and mass conflicts then creates all occurrences', function (string $component, string $source) {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community      = massCreationCommunity();
    $data           = $component === CreateSchedule::class ? recurringMassData($community) : extraordinaryMassData($community);
    $user           = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $existingMasses = 0;

    if ($source === 'event') {
        $event = Event::factory()->create(['name' => 'Detalhe privado']);
        $event->reservations()->create(['place_id' => $community->places()->where('name', 'Sacristia')->value('id'), 'reserved_from' => '2026-09-22 18:00', 'reserved_to' => '2026-09-22 18:45']);
        $spaces = ['Sacristia'];
    } else {
        Mass::factory()->create(['community_id' => $community->id, 'motivation' => 'Detalhe privado', 'starts_at' => '2026-09-22 20:15', 'ends_at' => '2026-09-22 21:15']);
        $existingMasses = 1;
        $spaces         = ['Nave', 'Sacristia', 'Estacionamento'];
    }

    $test = Livewire::actingAs($user)->test($component)->set($data)->call('save')->assertHasNoErrors()
        ->assertSet('conflicts', [['date' => '2026-09-22', 'places' => $spaces]])
        ->assertSee('22/09/2026')->assertSee('Cadastrar mesmo assim')->assertDontSee('Detalhe privado');

    $this->assertDatabaseCount('masses', $existingMasses);
    $this->assertDatabaseCount('mass_schedules', 0);
    $test->set('motivation', 'Alteração não confirmada')->call('confirm')->assertHasNoErrors()->assertDispatched('created')->assertSet('pending', null);
    $count = $component === CreateSchedule::class ? 3 : 1;
    $this->assertDatabaseCount('masses', $existingMasses + $count);
    $this->assertDatabaseMissing('masses', ['motivation' => 'Alteração não confirmada']);
    expect(Mass::query()->withReservationConflict()->where('motivation', $data['motivation'])->orderBy('starts_at')->first()->has_reservation_conflict)->toBeTrue();
    $test->call('confirm');
    $this->assertDatabaseCount('masses', $existingMasses + $count);
})->with([CreateSchedule::class, CreateExtraordinary::class])->with(['event', 'mass']);

it('cancels pending confirmation without creating anything', function (string $component) {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    Mass::factory()->create(['community_id' => $community->id, 'starts_at' => '2026-09-22 19:00', 'ends_at' => '2026-09-22 20:00']);
    $data = $component === CreateSchedule::class ? recurringMassData($community) : extraordinaryMassData($community);
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->test($component)->set($data)->call('save')->call('cancel')->assertSet('pending', null)
        ->call('confirm')->assertNotDispatched('created');

    $this->assertDatabaseCount('masses', 1);
    $this->assertDatabaseCount('mass_schedules', 0);
})->with([CreateSchedule::class, CreateExtraordinary::class]);

it('resets fields when canceling or closing a creation modal', function (string $component) {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $test = Livewire::actingAs($user)->test($component)
        ->set('community_id', 42)
        ->set('motivation', 'Rascunho')
        ->set('starts_at', '19:00')
        ->set('modal', true)
        ->set('modal', false);

    $test->assertSet('modal', false)
        ->assertSet('formKey', 1)
        ->assertSet('community_id', '')
        ->assertSet('motivation', '')
        ->assertSet('starts_at', '')
        ->assertSet('pending', null)
        ->assertSet('conflicts', []);

    if ($component === CreateSchedule::class) {
        $test->assertSet('duration_minutes', 60)->assertSet('dateRange', null);
    } else {
        $test->assertSet('date', '')->assertSet('ends_at', '');
    }
})->with([CreateSchedule::class, CreateExtraordinary::class]);

it('treats touching reservation boundaries as non conflicting', function (string $from, string $to) {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $event     = Event::factory()->create();
    $event->reservations()->create(['place_id' => $community->places()->where('name', 'Nave')->value('id'), 'reserved_from' => $from, 'reserved_to' => $to]);
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->test(CreateExtraordinary::class)->set(extraordinaryMassData($community))->call('save')->assertDispatched('created');

    expect(Mass::query()->withReservationConflict()->sole()->has_reservation_conflict)->toBeFalse();
})->with([['2026-09-22 17:00', '2026-09-22 18:30'], ['2026-09-22 20:30', '2026-09-22 21:30']]);

it('refuses retroactive direct generation and skips past dates when planning a range', function () {
    $this->travelTo(now()->setDate(2026, 9, 23)->startOfDay());
    $schedule = MassSchedule::factory()->create([...recurringMassData(massCreationCommunity()), 'valid_from' => '2026-09-01']);

    expect(fn () => $schedule->generateMassForDate(today()->subDay()))->toThrow(InvalidArgumentException::class);
    expect(array_column($schedule->occurrences(), 'starts_at'))->toBe(['2026-09-29 19:00:00', '2026-10-06 19:00:00']);
    $this->assertDatabaseCount('masses', 0);
});

it('lists every conflicting occurrence with only the affected space names', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $event     = Event::factory()->create(['name' => 'Nome sigiloso']);

    foreach ([['2026-09-22', 'Nave'], ['2026-09-29', 'Estacionamento']] as [$date, $name]) {
        $event->reservations()->create(['place_id' => $community->places()->where('name', $name)->value('id'), 'reserved_from' => $date.' 19:00', 'reserved_to' => $date.' 20:00']);
    }
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->test(CreateSchedule::class)->set(recurringMassData($community))->call('save')
        ->assertSet('conflicts', [
            ['date' => '2026-09-22', 'places' => ['Nave']],
            ['date' => '2026-09-29', 'places' => ['Estacionamento']],
        ])->assertDontSee('Nome sigiloso')->call('confirm')->assertDispatched('created');

    $this->assertDatabaseCount('masses', 3);
});

it('ignores spaces in other communities and unrelated spaces in the selected community', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $other     = massCreationCommunity();
    Mass::factory()->create(['community_id' => $other->id, 'starts_at' => '2026-09-22 19:00', 'ends_at' => '2026-09-22 20:00']);
    $event = Event::factory()->create();
    $hall  = $community->places()->create(['name' => 'Salão']);
    $event->reservations()->create(['place_id' => $hall->id, 'reserved_from' => '2026-09-22 19:00', 'reserved_to' => '2026-09-22 20:00']);
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->test(CreateExtraordinary::class)->set(extraordinaryMassData($community))->call('save')->assertDispatched('created');

    expect(Mass::query()->withReservationConflict()->where('community_id', $community->id)->sole()->has_reservation_conflict)->toBeFalse();
});

it('shows a live conflict indicator without per mass queries', function (string $source) {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $mass      = Mass::factory()->create(['community_id' => $community->id, 'starts_at' => '2026-09-22 19:00', 'ends_at' => '2026-09-22 20:00']);

    if ($source === 'event') {
        $event    = Event::factory()->create(['name' => 'Compromisso privado']);
        $conflict = $event->reservations()->create(['place_id' => $community->places()->where('name', 'Estacionamento')->value('id'), 'reserved_from' => '2026-09-22 18:00', 'reserved_to' => '2026-09-22 18:45']);
    } else {
        $other = Mass::factory()->create(['community_id' => $community->id, 'starts_at' => '2026-09-22 20:30', 'ends_at' => '2026-09-22 21:30']);
    }
    $user = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    DB::enableQueryLog();

    try {
        $test = Livewire::actingAs($user)->test(Index::class)->assertSee('Existe conflito de reserva de espaço neste horário.')
            ->assertDontSee('Compromisso privado')->assertViewHas('masses', fn ($rows): bool => $rows->firstWhere('id', $mass->id)->has_reservation_conflict);
        $initialQueries = count(DB::getQueryLog());
        Mass::factory()->count(5)->create(['community_id' => $community->id, 'starts_at' => '2026-10-01 12:00', 'ends_at' => '2026-10-01 13:00']);
        DB::flushQueryLog();
        Livewire::actingAs($user)->test(Index::class)->assertSee('Existe conflito de reserva de espaço neste horário.');
        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($initialQueries);
    } finally {
        DB::disableQueryLog();
    }

    if ($source === 'event') {
        $conflict->delete();
    } else {
        $other->reservations()->delete();
    }
    $test->call('$refresh')->assertViewHas('masses', fn ($rows): bool => ! $rows->firstWhere('id', $mass->id)->has_reservation_conflict);
})->with(['event', 'mass']);

it('rolls back the schedule all occurrences and reservations when generation fails', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    $this->actingAs(User::factory()->create(['roles' => ['admin'], 'is_active' => true]));
    $dispatcher = Mass::getEventDispatcher();
    Mass::setEventDispatcher(clone $dispatcher);
    Mass::creating(function (Mass $mass): void {
        if ($mass->starts_at->toDateString() === '2026-09-29') {
            throw new RuntimeException('Falha na segunda ocorrência');
        }
    });

    try {
        expect(fn () => app(CreateMassScheduleAction::class)->handle(recurringMassData($community)))
            ->toThrow(RuntimeException::class, 'Falha na segunda ocorrência');
    } finally {
        Mass::setEventDispatcher($dispatcher);
    }

    $this->assertDatabaseCount('mass_schedules', 0);
    $this->assertDatabaseCount('masses', 0);
    $this->assertDatabaseCount('place_reservations', 0);
});

it('rechecks permissions while allowing spaces to change at confirmation', function (string $change) {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = massCreationCommunity();
    Mass::factory()->create(['community_id' => $community->id, 'starts_at' => '2026-09-22 19:00', 'ends_at' => '2026-09-22 20:00']);
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $test = Livewire::actingAs($user)->test(CreateExtraordinary::class)->set(extraordinaryMassData($community))->call('save');

    if ($change === 'permissions') {
        $user->update(['is_active' => false]);
        $test->call('confirm')->assertForbidden();
    } else {
        $community->places()->where('name', 'Sacristia')->update(['name' => 'Antiga sacristia']);
        $test->call('confirm')->assertHasNoErrors()->assertDispatched('created');
    }

    $this->assertDatabaseCount('masses', $change === 'places' ? 2 : 1);
})->with(['permissions', 'places']);

it('keeps confirmation data locked against client tampering', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    expect(fn () => Livewire::actingAs($user)->test(CreateExtraordinary::class)->set('pending', ['community_id' => 1]))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

    $this->assertDatabaseCount('masses', 0);
});

it('includes today and handles reservations that cross midnight', function () {
    $this->travelTo(now()->setDate(2026, 9, 22)->startOfDay());
    $community = massCreationCommunity();
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);

    Livewire::actingAs($user)->test(CreateSchedule::class)
        ->set([...recurringMassData($community), 'valid_from' => '2026-09-22', 'valid_until' => '2026-09-22', 'starts_at' => '23:45', 'duration_minutes' => 90])
        ->call('save')->assertHasNoErrors()->assertDispatched('created');

    $this->assertDatabaseCount('masses', 1);
    $this->assertDatabaseHas('masses', ['starts_at' => '2026-09-22 23:45:00', 'ends_at' => '2026-09-23 01:15:00']);
    $this->assertDatabaseHas('place_reservations', ['reserved_from' => '2026-09-22 23:15:00', 'reserved_to' => '2026-09-23 01:45:00']);
});
