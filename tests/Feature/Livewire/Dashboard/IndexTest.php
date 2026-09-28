<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\EventLog;
use App\Models\Community;
use App\Models\MassSchedule;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Livewire\Dashboard\PascomOverview;
use App\Livewire\Dashboard\UpcomingEvents;
use App\Livewire\Dashboard\UpcomingMasses;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(9, 0));
});

it('redirects guests to login and pending registrations to approval', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->pending()->create())
        ->get(route('dashboard'))->assertRedirect(route('registration.pending'));
});

it('shows the first name and greeting for the current time', function (int $hour, string $greeting) {
    $this->travelTo(now()->setTime($hour, 0));
    $user = User::factory()->create(['name' => 'Josias Silva']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee($greeting.', Josias')
        ->assertDontSee($greeting.', Josias Silva')
        ->assertSee('28 de setembro')
        ->assertSee('Novo evento');
})->with([[9, 'Bom dia'], [15, 'Boa tarde'], [20, 'Boa noite']]);

it('shows only relevant refused events and confirmed commitments for members', function () {
    $user     = User::factory()->create();
    $ownGroup = Group::factory()->create();
    $ownGroup->users()->attach($user);
    $otherGroup = Group::factory()->create();
    Event::factory()->for($ownGroup)->create(['name' => 'Retiro precisa de ajustes', 'status' => EventStatusEnum::REFUSED]);
    Event::factory()->for($ownGroup)->create(['name' => 'Meu pedido em análise', 'status' => EventStatusEnum::PENDING]);
    Event::factory()->for($otherGroup)->create(['name' => 'Pedido protegido', 'status' => EventStatusEnum::PENDING]);
    Event::factory()->for($otherGroup)->create(['name' => 'Recusado protegido', 'status' => EventStatusEnum::REFUSED]);
    Event::factory()->for($otherGroup)->create(['name' => 'Evento geral confirmado', 'status' => EventStatusEnum::CONFIRMED]);
    User::factory()->pending()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Retiro precisa de ajustes')
        ->assertSee('Evento geral confirmado')
        ->assertDontSee('Pedido protegido')
        ->assertDontSee('Recusado protegido')
        ->assertDontSee('Meu pedido em análise')
        ->assertDontSee('Revisar eventos')
        ->assertDontSee('Aprovar usuários')
        ->assertDontSeeLivewire(PascomOverview::class)
        ->assertDontSeeLivewire(UpcomingMasses::class);
});

it('shows user approvals but no event review for secretaries', function () {
    $user = User::factory()->create(['roles' => ['secretary']]);
    User::factory()->pending()->count(2)->create();
    Event::factory()->create(['status' => EventStatusEnum::PENDING]);
    Event::factory()->create(['status' => EventStatusEnum::RESCHEDULED]);
    Event::factory()->create(['name' => 'Evento da secretaria recusado', 'created_by_user_id' => $user->id, 'status' => EventStatusEnum::REFUSED, 'group_id' => null]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('2 cadastros aguardam aprovação')
        ->assertSee('Evento da secretaria recusado')
        ->assertDontSee('Revisar eventos')
        ->assertDontSee('nova aprovação');
});

it('separates changed event reviews, new event reviews and user approvals for reviewers', function (array $roles) {
    $user = User::factory()->create(['roles' => $roles]);
    Event::factory()->count(2)->create(['status' => EventStatusEnum::RESCHEDULED]);
    Event::factory()->count(3)->create(['status' => EventStatusEnum::PENDING]);
    User::factory()->pending()->count(4)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSeeInOrder(['2 eventos alterados precisam de nova aprovação', '3 eventos aguardam aprovação', '4 cadastros aguardam aprovação'])
        ->assertSee(route('events.index', ['status' => 'rescheduled', 'period' => 'all']), false)
        ->assertSee(route('users.index', ['status' => 'pending']));
})->with([
    'cpp'              => [['cpp']],
    'admin'            => [['admin']],
    'cpp and pascom'   => [['cpp', 'pascom']],
    'admin and pascom' => [['admin', 'pascom']],
]);

it('counts only pending accounts the actor can approve', function (string $role, string $expected) {
    $user = User::factory()->create(['roles' => [$role]]);
    User::factory()->pending()->create();
    User::factory()->pending()->create(['roles' => ['admin']]);
    User::factory()->pending()->create(['roles' => ['cpp']]);
    User::factory()->create(['is_active' => false, 'deactivated_at' => now()]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee($expected);
})->with([['secretary', '1 cadastro aguarda aprovação'], ['cpp', '2 cadastros aguardam aprovação'], ['admin', '3 cadastros aguardam aprovação']]);

it('shows event review and real upcoming masses without user approval for priests', function () {
    $user = User::factory()->create(['roles' => ['priest']]);
    Event::factory()->create(['status' => EventStatusEnum::PENDING]);
    Event::factory()->create(['status' => EventStatusEnum::RESCHEDULED]);
    User::factory()->pending()->create();
    $schedule = MassSchedule::factory()->create(['motivation' => 'Celebração semanal']);
    Mass::factory()->create(['community_id' => $schedule->community_id, 'mass_schedule_id' => $schedule->id,
        'motivation'                        => null, 'starts_at' => now()->addHours(10), 'ends_at' => now()->addHours(11)]);
    Mass::factory()->create(['motivation' => 'Missa cancelada protegida', 'canceled_at' => now()]);
    Mass::factory()->create(['motivation' => 'Missa antiga', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('1 evento aguarda aprovação')
        ->assertSee('1 evento alterado precisa de nova aprovação')
        ->assertSeeLivewire(UpcomingMasses::class)
        ->assertSee('Celebração semanal')
        ->assertSee('19:00')
        ->assertDontSee('Missa cancelada protegida')
        ->assertDontSee('Missa antiga')
        ->assertDontSee('Aprovar usuários');
});

it('shows Pascom advertising and recent changes without approval actions', function () {
    $user         = User::factory()->create(['roles' => ['pascom']]);
    $advertisable = Event::factory()->create(['name' => 'Festa para divulgar', 'advertisable' => true, 'status' => EventStatusEnum::CONFIRMED]);
    $changed      = Event::factory()->create(['name' => 'Retiro remarcado', 'status' => EventStatusEnum::RESCHEDULED]);
    EventLog::create(['event_id' => $changed->id, 'action' => EventLogActionEnum::RESCHEDULED]);
    EventLog::create(['event_id' => $advertisable->id, 'action' => EventLogActionEnum::APPROVED]);
    $deleted = Event::factory()->create(['name' => 'Evento excluído protegido']);
    EventLog::create(['event_id' => $deleted->id, 'action' => EventLogActionEnum::CANCELED]);
    $deleted->delete();
    $old    = Event::factory()->create(['name' => 'Alteração antiga', 'status' => EventStatusEnum::PENDING]);
    $oldLog = EventLog::create(['event_id' => $old->id, 'action' => EventLogActionEnum::APPROVED]);
    $oldLog->forceFill(['created_at' => now()->subDays(15)])->save();
    User::factory()->pending()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSeeLivewire(PascomOverview::class)
        ->assertSee('Festa para divulgar')
        ->assertSee('Retiro remarcado')
        ->assertSee('Evento aprovado')
        ->assertDontSee('Evento excluído protegido')
        ->assertDontSee('Alteração antiga')
        ->assertDontSee('Revisar eventos')
        ->assertDontSee('Aprovar usuários');
});

it('combines Pascom with the other responsibilities without exclusive roles', function (array $roles, string $content) {
    $user = User::factory()->create(['roles' => $roles]);
    Event::factory()->create(['status' => EventStatusEnum::PENDING]);
    User::factory()->pending()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSeeLivewire(PascomOverview::class)
        ->assertSee('Eventos para divulgação')
        ->assertSee($content);
})->with([
    'cpp and pascom'       => [['cpp', 'pascom'], '1 evento aguarda aprovação'],
    'secretary and pascom' => [['secretary', 'pascom'], '1 cadastro aguarda aprovação'],
    'priest and pascom'    => [['priest', 'pascom'], 'Próximas missas'],
]);

it('omits queries for responsibilities unavailable to the user', function (string $role) {
    $user = User::factory()->create(['roles' => [$role]]);
    $this->actingAs($user);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->get(route('dashboard'))->assertDontSeeLivewire(UpcomingMasses::class);

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'from "masses"') || str_contains($sql, 'from "mass_schedules"')))->toBeEmpty();
    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'COUNT(*) as total')))->toBeEmpty();
    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'count(*) as aggregate') && str_contains($sql, 'from "users"')))->toBeEmpty();
})->with(['member', 'pascom']);

it('does not query Pascom data for users without that role', function (string $role) {
    $user = User::factory()->create(['roles' => [$role]]);
    $this->actingAs($user);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->get(route('dashboard'))->assertDontSeeLivewire(PascomOverview::class);

    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    expect($queries->filter(fn (array $entry): bool => str_contains($entry['query'], '"event_logs"') || str_contains($entry['query'], '"advertisable" =')))->toBeEmpty();
})->with(['member', 'secretary', 'cpp', 'admin', 'priest']);

it('forbids specialized components when requested directly without their role', function (string $component) {
    Livewire::actingAs(User::factory()->create())->test($component)->assertForbidden();
})->with([PascomOverview::class, UpcomingMasses::class]);

it('rechecks access on component updates after a role is revoked', function () {
    $user      = User::factory()->create(['roles' => ['pascom']]);
    $component = Livewire::actingAs($user)->test(PascomOverview::class);
    $user->update(['roles' => ['member']]);

    $component->refresh()->assertForbidden();
});

it('prioritizes confirmed events from member groups and excludes canceled or tentative events', function () {
    $user  = User::factory()->create();
    $group = Group::factory()->create();
    $group->users()->attach($user);
    Event::factory()->create(['group_id' => null, 'name' => 'Evento geral primeiro', 'status' => EventStatusEnum::CONFIRMED,
        'starts_at'                      => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]);
    Event::factory()->for($group)->create(['name' => 'Compromisso do meu grupo', 'status' => EventStatusEnum::CONFIRMED,
        'starts_at'                               => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour()]);
    Event::factory()->for($group)->create(['name' => 'Evento cancelado', 'status' => EventStatusEnum::CANCELED]);
    Event::factory()->for($group)->create(['name' => 'Evento recusado', 'status' => EventStatusEnum::REFUSED]);
    Event::factory()->create(['name' => 'Compromisso passado', 'status' => EventStatusEnum::CONFIRMED,
        'starts_at'                  => now()->subDays(2), 'ends_at' => now()->subDay()]);

    Livewire::actingAs($user)->test(UpcomingEvents::class)
        ->assertSeeInOrder(['Compromisso do meu grupo', 'Evento geral primeiro'])
        ->assertSee('Seu grupo')
        ->assertDontSee('Evento cancelado')
        ->assertDontSee('Evento recusado')
        ->assertDontSee('Compromisso passado');
});

it('limits upcoming events and eagerly loads their displayed relations', function () {
    $user      = User::factory()->create(['roles' => ['admin']]);
    $group     = Group::factory()->create(['name' => 'Coroinhas']);
    $community = Community::create(['name' => 'São Marcos', 'alias' => 'sao-marcos', 'abbreviation' => 'SM']);
    $mainPlace = $community->places()->create(['name' => 'Igreja']);
    $place     = $community->places()->create(['name' => 'Nave', 'main_place_id' => $mainPlace->id]);
    $events    = Event::factory()->count(6)->for($group)->for($community)->create(['status' => EventStatusEnum::CONFIRMED]);

    foreach ($events as $event) {
        PlaceReservation::factory()->for($event)->for($place)->create(['is_primary' => true]);
    }
    Model::preventLazyLoading();

    try {
        Livewire::actingAs($user)->test(UpcomingEvents::class)
            ->assertViewHas('events', fn ($events): bool => $events->count() === 5)
            ->assertSee('Coroinhas')->assertSee('São Marcos')->assertSee('Igreja: Nave');
    } finally {
        Model::preventLazyLoading(false);
    }
});

it('shows simple empty states when there are no relevant records', function () {
    $user = User::factory()->create(['roles' => ['priest', 'pascom']]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('Tudo em dia')
        ->assertSee('Você não possui nenhuma pendência no momento.')
        ->assertSee('Nenhum evento próximo.')
        ->assertSee('Nenhum evento próximo para divulgação.')
        ->assertSee('Nenhuma alteração recente.')
        ->assertSee('Nenhuma missa próxima.');
});

it('escapes event names in the dashboard', function () {
    Event::factory()->create(['name' => '<script>alert(1)</script>', 'status' => EventStatusEnum::CONFIRMED]);

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('does not load external details for internal commitments', function () {
    Event::factory()->create(['is_external' => false, 'status' => EventStatusEnum::CONFIRMED]);
    $user = User::factory()->create();
    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::actingAs($user)->test(UpcomingEvents::class)->assertSee('Próximos eventos');

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    expect($queries->filter(fn (string $sql): bool => str_contains($sql, '"event_details"')))->toBeEmpty();
});

it('loads external locations without querying reservations for external commitments', function () {
    $event = Event::factory()->create(['is_external' => true, 'status' => EventStatusEnum::CONFIRMED]);
    $event->detail()->create(['external_location_name' => 'Casa de retiros']);
    $user = User::factory()->create();
    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::actingAs($user)->test(UpcomingEvents::class)->assertSee('Casa de retiros');

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    expect($queries->filter(fn (string $sql): bool => str_contains($sql, '"place_reservations"')))->toBeEmpty();
});

it('filters advertising events by confirmation, future date and advertising permission', function () {
    $user = User::factory()->create(['roles' => ['pascom']]);
    Event::factory()->create(['name' => 'Festa anunciável', 'status' => EventStatusEnum::CONFIRMED, 'advertisable' => true]);
    Event::factory()->create(['name' => 'Evento sem divulgação', 'status' => EventStatusEnum::CONFIRMED, 'advertisable' => false]);
    Event::factory()->create(['name' => 'Pedido de divulgação pendente', 'status' => EventStatusEnum::PENDING, 'advertisable' => true]);
    Event::factory()->create(['name' => 'Festa já encerrada', 'status' => EventStatusEnum::CONFIRMED, 'advertisable' => true,
        'starts_at'                  => now()->subDays(2), 'ends_at' => now()->subDay()]);

    Livewire::actingAs($user)->test(PascomOverview::class)
        ->assertSee('Festa anunciável')
        ->assertDontSee('Evento sem divulgação')
        ->assertDontSee('Pedido de divulgação pendente')
        ->assertDontSee('Festa já encerrada');
});

it('shows refused events created outside groups to actors who can adjust them', function (string $role) {
    $user  = User::factory()->create(['roles' => [$role]]);
    $event = Event::factory()->create(['group_id' => null, 'name' => 'Evento próprio recusado',
        'created_by_user_id'                      => $user->id, 'status' => EventStatusEnum::REFUSED]);

    $this->actingAs($user)->get(route('dashboard'))->assertSee($event->name);
})->with(['secretary', 'priest', 'cpp', 'admin']);

it('hides refused events outside groups when creation alone does not permit adjustments', function (string $role) {
    $user  = User::factory()->create(['roles' => [$role]]);
    $event = Event::factory()->create(['group_id' => null, 'name' => 'Evento próprio recusado',
        'created_by_user_id'                      => $user->id, 'status' => EventStatusEnum::REFUSED]);

    $this->actingAs($user)->get(route('dashboard'))->assertDontSee($event->name);
})->with(['member', 'pascom']);

it('does not query user approvals for priests', function () {
    $user = User::factory()->create(['roles' => ['priest']]);
    $this->actingAs($user);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $this->get(route('dashboard'))->assertDontSee('Aprovar usuários');

    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();
    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'count(*) as aggregate') && str_contains($sql, 'from "users"')))->toBeEmpty();
});
