<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Models\PlaceReservation;
use App\Livewire\Events\Forms\Recurring;

beforeEach(function () {
    $this->travelTo('2026-09-21 10:00:00');
});

/** @return array<string, mixed> */
function recurringRegistrationData(): array
{
    $group     = Group::factory()->create();
    $community = Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);
    $place = $community->places()->create(['name' => 'Salão']);

    return [
        'group_id'            => $group->id,
        'name'                => 'Encontro semanal',
        'type'                => EventTypeEnum::COURSE->value,
        'weekday'             => '2',
        'periodicity'         => 'weekly',
        'monthly_position'    => '',
        'start_date'          => '2026-09-22',
        'end_date'            => '2026-10-06',
        'starts_time'         => '09:00',
        'ends_time'           => '11:00',
        'is_public'           => true,
        'confirm_immediately' => false,
        'community_id'        => $community->id,
        'place_ids'           => [$place->id],
        'place_hours'         => [$place->id => ['before' => 0, 'after' => 0]],
    ];
}

it('generates and saves the weekly occurrences in the selected period', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $data = recurringRegistrationData();

    $component = Livewire::actingAs($user)->test(Recurring::class)
        ->set($data)
        ->call('validateDraft')
        ->assertHasNoErrors()
        ->assertSet('dates', ['2026-09-22', '2026-09-29', '2026-10-06'])
        ->assertSet('draftValidated', true);

    $component->call('save')->assertRedirect(route('events.index'));

    $events = Event::query()->orderBy('starts_at')->get();

    expect($events->pluck('starts_at')->map->format('Y-m-d')->all())->toBe(['2026-09-22', '2026-09-29', '2026-10-06'])
        ->and($events->pluck('recurrence_code')->unique()->values())->toHaveCount(1);
    $this->assertDatabaseCount('place_reservations', 3);
});

it('requires a monthly position and rejects a period without future occurrences', function () {
    $user = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $data = recurringRegistrationData();

    $component = Livewire::actingAs($user)->test(Recurring::class)
        ->set([...$data, 'periodicity' => 'monthly'])
        ->call('validateDraft')
        ->assertHasErrors(['monthly_position']);

    $component
        ->set([...$data, 'start_date' => '2027-01-01', 'end_date' => null])
        ->call('validateDraft')
        ->assertHasErrors(['end_date']);

    $this->assertDatabaseCount('events', 0);
});

it('identifies conflicts on specific dates and saves the remaining occurrences', function () {
    $user          = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $data          = recurringRegistrationData();
    $existingEvent = Event::factory()->create([
        'starts_at' => '2026-09-29 09:30:00',
        'ends_at'   => '2026-09-29 10:30:00',
    ]);
    PlaceReservation::create([
        'event_id'      => $existingEvent->id,
        'place_id'      => $data['place_ids'][0],
        'reserved_from' => '2026-09-29 09:30:00',
        'reserved_to'   => '2026-09-29 10:30:00',
        'is_primary'    => true,
    ]);

    $component = Livewire::actingAs($user)->test(Recurring::class)
        ->set($data)
        ->call('validateDraft')
        ->assertHasNoErrors()
        ->assertSet('conflictsSlide', true)
        ->assertSee('29/09/2026');

    expect($component->get('occurrences')['2026-09-29']['status'])->toBe('conflict');

    $component->call('removeConflictingPlaces', '2026-09-29')
        ->assertSet('draftValidated', true)
        ->call('save')
        ->assertRedirect(route('events.index'));

    expect(Event::query()->where('name', 'Encontro semanal')->orderBy('starts_at')->get()
        ->pluck('starts_at')->map->format('Y-m-d')->all())->toBe(['2026-09-22', '2026-10-06']);
});
