<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use App\Models\Community;
use App\Models\PlaceReservation;
use Illuminate\Database\Eloquent\Collection;
use App\Actions\CheckPlaceAvailabilityAction;

function availabilityCommunity(): Community
{
    return Community::create([
        'name'         => fake()->unique()->company(),
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => mb_strtoupper(fake()->unique()->lexify('???')),
    ]);
}

function reservePlace(Place $place, string $reservedFrom, string $reservedTo): PlaceReservation
{
    $event = Event::factory()->for(Group::factory())->create([
        'starts_at' => $reservedFrom,
        'ends_at'   => $reservedTo,
    ]);

    return PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => $reservedFrom,
        'reserved_to'   => $reservedTo,
        'is_primary'    => true,
    ]);
}

function checkAvailability(Place $place, float $beforeHours = 0, float $afterHours = 0): array
{
    return app(CheckPlaceAvailabilityAction::class)->handle(
        '2026-10-10 10:00:00',
        '2026-10-10 11:00:00',
        new Collection([$place]),
        [$place->id => ['before_hours' => $beforeHours, 'after_hours' => $afterHours]],
    );
}

it('returns no conflicts when the requested place is free', function () {
    $place = availabilityCommunity()->places()->create(['name' => 'Salão']);
    reservePlace($place, '2026-10-10 09:00:00', '2026-10-10 10:00:00');
    reservePlace($place, '2026-10-10 11:00:00', '2026-10-10 12:00:00');

    $conflicts = checkAvailability($place);

    expect($conflicts)->toBe([]);
});

it('returns a direct conflict with the requested and reserved intervals', function () {
    $place = availabilityCommunity()->places()->create(['name' => 'Salão']);
    reservePlace($place, '2026-10-10 10:30:00', '2026-10-10 11:30:00');

    $conflicts = checkAvailability($place);

    expect($conflicts)->toBe([
        [
            'requested_place'         => ['id' => $place->id, 'name' => 'Salão'],
            'requested_reserved_from' => '2026-10-10 10:00:00',
            'requested_reserved_to'   => '2026-10-10 11:00:00',
            'conflicts'               => [[
                'reserved_place' => ['id' => $place->id, 'name' => 'Salão'],
                'reserved_from'  => '2026-10-10 10:30:00',
                'reserved_to'    => '2026-10-10 11:30:00',
            ]],
        ],
    ]);
});

it('returns a conflict from the parent when a child is requested', function () {
    $community = availabilityCommunity();
    $parent    = $community->places()->create(['name' => 'Centro Catequético']);
    $child     = $community->places()->create(['name' => 'Sala 1', 'main_place_id' => $parent->id]);
    reservePlace($parent, '2026-10-10 09:30:00', '2026-10-10 10:30:00');

    $conflicts = checkAvailability($child);

    expect($conflicts[0]['requested_place'])->toBe(['id' => $child->id, 'name' => 'Sala 1'])
        ->and($conflicts[0]['conflicts'][0]['reserved_place'])->toBe(['id' => $parent->id, 'name' => 'Centro Catequético']);
});

it('returns a conflict from a child when the parent is requested', function () {
    $community = availabilityCommunity();
    $parent    = $community->places()->create(['name' => 'Centro Catequético']);
    $child     = $community->places()->create(['name' => 'Sala 1', 'main_place_id' => $parent->id]);
    reservePlace($child, '2026-10-10 10:45:00', '2026-10-10 12:00:00');

    $conflicts = checkAvailability($parent);

    expect($conflicts[0]['requested_place'])->toBe(['id' => $parent->id, 'name' => 'Centro Catequético'])
        ->and($conflicts[0]['conflicts'][0]['reserved_place'])->toBe(['id' => $child->id, 'name' => 'Sala 1']);
});

it('applies different before and after hour buffers in fifteen minute increments', function () {
    $place = availabilityCommunity()->places()->create(['name' => 'Salão']);
    reservePlace($place, '2026-10-10 08:45:00', '2026-10-10 09:15:00');
    reservePlace($place, '2026-10-10 11:30:00', '2026-10-10 12:00:00');

    $conflicts = checkAvailability($place, beforeHours: 1, afterHours: 0.75);

    expect($conflicts[0]['requested_reserved_from'])->toBe('2026-10-10 09:00:00')
        ->and($conflicts[0]['requested_reserved_to'])->toBe('2026-10-10 11:45:00')
        ->and($conflicts[0]['conflicts'])->toHaveCount(2);
});
