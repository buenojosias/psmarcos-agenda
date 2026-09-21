<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\Event;
use App\Models\Community;
use App\Models\MassSchedule;
use App\Models\PlaceReservation;

it('generates a concrete mass from a valid regular schedule', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->startOfDay());
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $community->places()->createMany(array_map(fn (string $name): array => ['name' => $name], ['Nave', 'Sacristia', 'Estacionamento']));
    $schedule = MassSchedule::factory()->create([
        'community_id'     => $community->id,
        'weekday'          => 0,
        'starts_at'        => '08:30:00',
        'duration_minutes' => 75,
        'motivation'       => 'Missa Dominical',
        'valid_from'       => '2026-09-01',
        'valid_until'      => '2026-09-30',
    ]);

    $mass = $schedule->generateMassForDate(now()->setDate(2026, 9, 20));

    expect($mass)
        ->mass_schedule_id->toBe($schedule->id)
        ->community_id->toBe($community->id)
        ->motivation->toBe('Missa Dominical')
        ->and($mass->starts_at->format('Y-m-d H:i:s'))->toBe('2026-09-20 08:30:00')
        ->and($mass->ends_at->format('Y-m-d H:i:s'))->toBe('2026-09-20 09:45:00');
});

it('creates default place reservations when a mass is created', function () {
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $primary   = $community->places()->create(['name' => 'Nave']);
    $secondary = $community->places()->create(['name' => 'Sacristia']);
    $community->massPlaces()->attach([
        $primary->id   => ['is_primary' => true],
        $secondary->id => ['is_primary' => false],
    ]);

    $mass = Mass::factory()->create([
        'community_id' => $community->id,
        'starts_at'    => '2026-09-20 08:00:00',
        'ends_at'      => '2026-09-20 09:00:00',
    ]);

    expect($mass->reservations()->count())->toBe(3)
        ->and($mass->primaryReservation()->value('place_id'))->toBe($primary->id);

    $mass->reservations()->each(function (PlaceReservation $reservation): void {
        expect($reservation->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-09-20 07:30:00')
            ->and($reservation->reserved_to->format('Y-m-d H:i:s'))->toBe('2026-09-20 09:30:00');
    });
});

it('requires a place reservation to belong to exactly one event or mass', function () {
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Nave']);
    $event     = Event::factory()->create();
    $mass      = Mass::factory()->create(['community_id' => $community->id]);
    $mass->reservations()->delete();

    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => $event->starts_at,
        'reserved_to'   => $event->ends_at,
    ]);

    PlaceReservation::create([
        'mass_id'       => $mass->id,
        'place_id'      => $place->id,
        'reserved_from' => $mass->starts_at,
        'reserved_to'   => $mass->ends_at,
    ]);

    expect(fn () => PlaceReservation::create([
        'event_id'      => $event->id,
        'mass_id'       => $mass->id,
        'place_id'      => $place->id,
        'reserved_from' => now(),
        'reserved_to'   => now()->addHour(),
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => PlaceReservation::create([
        'place_id'      => $place->id,
        'reserved_from' => now(),
        'reserved_to'   => now()->addHour(),
    ]))->toThrow(InvalidArgumentException::class);
});
