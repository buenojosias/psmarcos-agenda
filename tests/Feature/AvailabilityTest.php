<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\DB;
use App\Livewire\Availability\Index;
use App\Actions\PlaceAvailabilityAction;
use App\Actions\CheckMassConflictsAction;
use Illuminate\Database\Eloquent\Collection;
use App\Actions\CheckPlaceAvailabilityAction;

beforeEach(function () {
    $this->withoutVite();
    $this->community    = Community::create(['name' => 'São Marcos', 'alias' => 'sao-marcos', 'abbreviation' => 'SM']);
    $this->place        = $this->community->places()->create(['name' => 'Sala de reuniões']);
    $this->member       = User::factory()->create(['is_active' => true, 'roles' => ['member']]);
    $this->availability = app(PlaceAvailabilityAction::class);
});

function mapReservation(Place $place, string $start = '2026-09-30 14:10:00', string $end = '2026-09-30 15:35:00', EventStatusEnum $status = EventStatusEnum::CONFIRMED, ?Group $group = null): Event
{
    $event = Event::factory()->create([
        'community_id' => $place->community_id,
        'name'         => 'Encontro confidencial', 'complement' => 'Complemento privado',
        'starts_at'    => $start, 'ends_at' => $end, 'status' => $status,
        'group_id'     => ($group ?? Group::factory()->create(['name' => 'Grupo sigiloso']))->id,
    ]);
    PlaceReservation::factory()->create([
        'event_id'      => $event->id, 'place_id' => $place->id,
        'reserved_from' => $start, 'reserved_to' => $end,
    ]);

    return $event;
}

function mapComponent(Community $community, User $user, array $params = []): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::actingAs($user)->withQueryParams($params + ['comunidade' => $community->id, 'data' => '2026-09-30'])->test(Index::class);
}

it('returns a free row without reservations', function () {
    expect($this->availability->forDate($this->community, '2026-09-30', $this->member)[0]['periods'])->toBe([])
        ->and($this->availability->isAvailable($this->place, '2026-09-30 14:10', '2026-09-30 15:35'))->toBeTrue();
});

it('blocks direct reservations with exact minutes', function () {
    mapReservation($this->place);
    $period = $this->availability->forPlace($this->place, '2026-09-30', 1, $this->member)[0]['periods'][0];
    expect($period['start'])->toBe('2026-09-30 14:10:00')->and($period['end'])->toBe('2026-09-30 15:35:00')
        ->and($this->availability->isAvailable($this->place, '2026-09-30 15:34', '2026-09-30 16:00'))->toBeFalse();
});

it('blocks ancestors and descendants but keeps siblings available', function (string $reservedName) {
    $parent     = $this->community->places()->create(['name' => 'Centro pastoral']);
    $child      = $this->community->places()->create(['name' => 'Sala A', 'main_place_id' => $parent->id]);
    $grandchild = $this->community->places()->create(['name' => 'Sala A interna', 'main_place_id' => $child->id]);
    $sibling    = $this->community->places()->create(['name' => 'Sala B', 'main_place_id' => $parent->id]);
    $reserved   = match ($reservedName) {
        'parent' => $parent, 'child' => $child, 'grandchild' => $grandchild
    };
    mapReservation($reserved);

    foreach ([$parent, $child, $grandchild] as $place) {
        expect($this->availability->isAvailable($place, '2026-09-30 14:30', '2026-09-30 15:00'))->toBeFalse();
        expect($this->availability->forPlace($place, '2026-09-30', 1, $this->member)[0]['periods'])->toHaveCount(1);
        $conflicts = app(CheckPlaceAvailabilityAction::class)->handle('2026-09-30 14:30', '2026-09-30 15:00', new Collection([$place]), []);
        expect($conflicts)->toHaveCount(1);
    }
    expect($this->availability->isAvailable($sibling, '2026-09-30 14:30', '2026-09-30 15:00'))->toBe($reservedName !== 'parent');
})->with(['parent', 'child', 'grandchild']);

it('uses the same hierarchy for mass conflict validation', function () {
    $child = $this->community->places()->create(['name' => 'Sala interna', 'main_place_id' => $this->place->id]);
    mapReservation($child);
    expect(app(CheckMassConflictsAction::class)->handle(new Collection([$this->place]), [['starts_at' => '2026-09-30 14:40', 'ends_at' => '2026-09-30 15:00']]))
        ->toBe([['date' => '2026-09-30', 'places' => [$this->place->name]]]);
});

it('allows details of confirmed events from other groups using the existing policy', function () {
    $event = mapReservation($this->place);
    expect($this->member->can('view', $event))->toBeTrue();
    $period = $this->availability->forDate($this->community, '2026-09-30', $this->member)[0]['periods'][0];
    expect($period['visible'])->toBeTrue()->and($period['details'][0]['event_id'])->toBe($event->id);
    mapComponent($this->community, $this->member)->call('openPeriod', 0, 0)->assertSet('detail.label', $event->name)->assertSee('Ver evento');
});

it('sanitizes every restricted status in backend data and Livewire payloads', function (EventStatusEnum $status) {
    $event = mapReservation($this->place, status: $status);
    expect($this->member->can('view', $event))->toBeFalse();
    $rows = $this->availability->forDate($this->community, '2026-09-30', $this->member);
    expect($rows[0]['periods'])->toBe([[
        'start'   => '2026-09-30 14:10:00', 'end' => '2026-09-30 15:35:00',
        'visible' => false, 'label' => 'Horário indisponível', 'type' => 'restricted',
    ]]);
    $component = mapComponent($this->community, $this->member)->assertDontSee($event->name)->assertDontSee('Grupo sigiloso')->assertDontSee('Complemento privado')
        ->call('openPeriod', 0, 0)->assertSet('detail.visible', false)->assertDontSee('Ver evento');
    $payload = json_encode([$component->get('rows'), $component->get('detail')]);
    expect($payload)->not->toContain('event_id', 'group', 'status', $event->name, 'Grupo sigiloso', 'Complemento privado');
    expect($component->html())->not->toContain($event->name, 'Grupo sigiloso', 'Complemento privado');
})->with([EventStatusEnum::PENDING, EventStatusEnum::RESCHEDULED, EventStatusEnum::REFUSED]);

it('shows all statuses from the users own groups', function (EventStatusEnum $status) {
    $group = Group::factory()->create();
    $this->member->groups()->attach($group);
    $event  = mapReservation($this->place, status: $status, group: $group);
    $period = $this->availability->forDate($this->community, '2026-09-30', $this->member)[0]['periods'][0];
    expect($period['visible'])->toBeTrue()->and($period['details'][0]['event_id'])->toBe($event->id);
})->with(EventStatusEnum::cases());

it('does not expose restricted sources inherited through the hierarchy', function () {
    $child = $this->community->places()->create(['name' => 'Sala interna', 'main_place_id' => $this->place->id]);
    mapReservation($child, status: EventStatusEnum::PENDING);
    $row = $this->availability->forPlace($this->place, '2026-09-30', 1, $this->member)[0];
    expect(json_encode($row))->not->toContain('event_id', 'Grupo sigiloso', 'Sala interna', 'Encontro confidencial');
});

it('removes event details if membership is revoked between Livewire requests', function () {
    $group = Group::factory()->create();
    $this->member->groups()->attach($group);
    $event     = mapReservation($this->place, status: EventStatusEnum::PENDING, group: $group);
    $component = mapComponent($this->community, $this->member)->assertSee($event->name);
    $this->member->groups()->detach($group);
    $component->call('openPeriod', 0, 0)->assertSet('detail.visible', false)->assertDontSee($event->name);
    expect(json_encode($component->get('rows')))->not->toContain('event_id', $event->name);
});

it('blocks masses including their preparation and cleanup without linking to events', function () {
    $mass   = Mass::factory()->create(['community_id' => $this->community->id, 'starts_at' => '2026-09-30 19:05', 'ends_at' => '2026-09-30 20:10', 'motivation' => 'Missa com novena']);
    $place  = $this->community->places()->where('name', 'Nave')->firstOrFail();
    $period = $this->availability->forPlace($place, '2026-09-30', 1, $this->member)[0]['periods'][0];
    expect($period['type'])->toBe('mass')->and($period['start'])->toBe('2026-09-30 18:35:00')->and($period['end'])->toBe('2026-09-30 20:40:00');
    expect(json_encode($period))->not->toContain('event_id');
    mapComponent($this->community, $this->member, ['view' => 'ambiente', 'ambiente' => $place->id])->call('openPeriod', 0, 0)->assertSee($mass->motivation)->assertDontSee('Ver evento');
});

it('does not create false conflicts for adjacent intervals', function () {
    mapReservation($this->place, '2026-09-30 14:10', '2026-09-30 15:35');
    expect($this->availability->isAvailable($this->place, '2026-09-30 15:35', '2026-09-30 16:00'))->toBeTrue()
        ->and($this->availability->isAvailable($this->place, '2026-09-30 14:00', '2026-09-30 14:10'))->toBeTrue();
});

it('clips overnight reservations per day without limiting the domain to the visual hours', function () {
    mapReservation($this->place, '2026-09-30 23:10', '2026-10-01 07:05');
    $rows = $this->availability->forPlace($this->place, '2026-09-30', 2, $this->member);
    expect($rows[0]['periods'][0]['end'])->toBe('2026-10-01 00:00:00')
        ->and($rows[1]['periods'][0]['start'])->toBe('2026-10-01 00:00:00')
        ->and($rows[1]['periods'][0]['end'])->toBe('2026-10-01 07:05:00')
        ->and($this->availability->isAvailable($this->place, '2026-10-01 05:00', '2026-10-01 05:10'))->toBeFalse();
});

it('merges restricted intervals while preserving privacy in overlaps with visible events', function () {
    mapReservation($this->place, '2026-09-30 08:00', '2026-09-30 10:00');
    mapReservation($this->place, '2026-09-30 09:00', '2026-09-30 11:00', EventStatusEnum::PENDING);
    mapReservation($this->place, '2026-09-30 10:30', '2026-09-30 12:00', EventStatusEnum::PENDING);
    $periods = $this->availability->forPlace($this->place, '2026-09-30', 1, $this->member)[0]['periods'];
    expect($periods)->toHaveCount(2)->and($periods[0]['end'])->toBe('2026-09-30 09:00:00')
        ->and($periods[1])->toBe(['start' => '2026-09-30 09:00:00', 'end' => '2026-09-30 12:00:00', 'visible' => false, 'label' => 'Horário indisponível', 'type' => 'restricted']);
});

it('filters communities and resets places from another community', function () {
    $other      = Community::create(['name' => 'Santa Maria', 'alias' => 'santa-maria', 'abbreviation' => 'MA']);
    $otherPlace = $other->places()->create(['name' => 'Sala de outra comunidade']);
    mapReservation($otherPlace);
    $component = mapComponent($this->community, $this->member, ['view' => 'ambiente', 'ambiente' => $otherPlace->id])
        ->assertSet('placeId', '')->assertSet('rows', []);
    $component->set('mode', 'data')->assertDontSee('Sala de outra comunidade');
    expect($component->get('rows'))->toHaveCount(1);
    $component->set('mode', 'ambiente')->set('placeId', (string) $this->place->id)->assertCount('rows', 14)
        ->set('communityId', (string) $other->id)->assertSet('placeId', '')->assertSet('rows', []);
});

it('reconstructs url state and loads only the next fourteen days idempotently', function () {
    $component = mapComponent($this->community, $this->member, ['view' => 'ambiente', 'ambiente' => $this->place->id])->assertCount('rows', 14);
    expect($component->get('rows')[13]['date'])->toBe('2026-10-13');
    $generation = $component->get('generation');
    DB::enableQueryLog();
    DB::flushQueryLog();
    $component->call('loadMore', 14, $generation)->assertCount('rows', 28);
    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "place_reservations"'));
    DB::disableQueryLog();
    expect($queries)->toHaveCount(1);
    expect(collect($queries->first()['bindings'])->map(fn ($value) => $value instanceof Carbon\CarbonInterface ? $value->format('Y-m-d H:i:s') : $value)->all())->toContain('2026-10-14 00:00:00', '2026-10-28 00:00:00');
    expect($component->get('rows')[14]['date'])->toBe('2026-10-14')->and($component->get('rows')[27]['date'])->toBe('2026-10-27');
    $component->call('loadMore', 14, $generation)->assertCount('rows', 28);
});

it('resets the infinite scroll after changing date or place and ignores stale loads', function () {
    $second     = $this->community->places()->create(['name' => 'Outra sala']);
    $component  = mapComponent($this->community, $this->member, ['view' => 'ambiente', 'ambiente' => $this->place->id]);
    $generation = $component->get('generation');
    $component->call('loadMore', 14, $generation)->set('placeId', (string) $second->id)->assertCount('rows', 14);
    expect($component->get('rows')[0]['place_id'])->toBe($second->id);
    $component->call('loadMore', 14, $generation)->assertCount('rows', 14)
        ->set('date', '2026-11-10')->assertCount('rows', 14)->assertSet('rows.0.date', '2026-11-10');
});

it('validates malformed url values and supports date navigation', function () {
    mapComponent($this->community, $this->member, ['view' => 'invalid', 'comunidade' => ['bad'], 'ambiente' => ['bad'], 'data' => '2026-02-30'])
        ->assertSet('mode', 'data')->assertSet('communityId', (string) $this->community->id)->assertSet('placeId', '')
        ->assertSet('date', today()->toDateString())->set('date', '2026-09-30')
        ->call('previousDay')->assertSet('date', '2026-09-29')->call('nextDay')->assertSet('date', '2026-09-30')
        ->call('today')->assertSet('date', today()->toDateString());
});

it('requires authentication and active users at the route and domain boundaries', function () {
    $this->get(route('availability.index'))->assertRedirect(route('login'));
    $inactive = User::factory()->create(['is_active' => false, 'roles' => ['member']]);
    $this->actingAs($inactive)->get(route('availability.index'))->assertForbidden();
    expect(fn () => $this->availability->forDate($this->community, '2026-09-30', $inactive))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('renders the authenticated route and empty community state', function () {
    $this->actingAs($this->member)->get(route('availability.index'))->assertOk()->assertSee('Mapa de disponibilidade');
    $this->place->delete();
    mapComponent($this->community, $this->member)->assertSee('Esta comunidade ainda não possui ambientes cadastrados.');
    $this->community->delete();
    Livewire::actingAs($this->member)->test(Index::class)->assertSee('Nenhuma comunidade acessível.');
});

it('never serializes restricted event details in the full initial HTTP response', function () {
    $event    = mapReservation($this->place, status: EventStatusEnum::PENDING);
    $response = $this->actingAs($this->member)->get(route('availability.index', ['comunidade' => $this->community->id, 'data' => '2026-09-30']))->assertOk();
    expect($response->getContent())->not->toContain($event->name, 'Grupo sigiloso', 'Complemento privado');
});

it('escapes environment names and visible event titles including tooltip content', function () {
    $name = '<img src=x onerror=alert(1)>';
    $this->place->update(['name' => $name]);
    $event = mapReservation($this->place);
    $event->update(['name' => '<script>alert(2)</script>']);
    $component = mapComponent($this->community, $this->member)->assertSee($name)->assertSee($event->name);
    expect($component->html())->not->toContain($name, $event->name);
    $component->call('openPeriod', 0, 0);
    expect($component->html())->not->toContain($name, $event->name);
});

it('rechecks the existing mass visibility scope between requests', function () {
    $mass      = Mass::factory()->create(['community_id' => $this->community->id, 'starts_at' => '2026-09-30 19:00', 'ends_at' => '2026-09-30 20:00', 'motivation' => 'Motivação da missa']);
    $place     = $this->community->places()->where('name', 'Nave')->firstOrFail();
    $component = mapComponent($this->community, $this->member, ['view' => 'ambiente', 'ambiente' => $place->id])->assertSee($mass->motivation);
    $mass->update(['canceled_at' => now()]);
    $component->call('openPeriod', 0, 0)->assertSet('detail.visible', false)->assertDontSee($mass->motivation);
    expect(json_encode([$component->get('rows'), $component->get('detail')]))->not->toContain('mass_id', $mass->motivation);
});
