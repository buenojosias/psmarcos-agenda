<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\Event;
use App\Models\PlaceReservation;

it('aligns event and mass reservation windows to quarter hours', function () {
    $event = Event::factory()->create([
        'starts_at' => '2026-10-10 14:19:00',
        'ends_at'   => '2026-10-10 19:51:00',
    ]);
    $mass = Mass::factory()->create([
        'starts_at' => '2026-10-11 14:19:00',
        'ends_at'   => '2026-10-11 19:51:00',
    ]);

    $eventReservation = PlaceReservation::factory()->make();
    $massReservation  = PlaceReservation::factory()->forMass($mass)->make();

    foreach ([[$eventReservation, $event], [$massReservation, $mass]] as [$reservation, $source]) {
        expect($reservation->reserved_from->minute % 15)->toBe(0)
            ->and($reservation->reserved_from->second)->toBe(0)
            ->and($reservation->reserved_to->minute % 15)->toBe(0)
            ->and($reservation->reserved_to->second)->toBe(0)
            ->and($reservation->reserved_from->lessThan($source->starts_at))->toBeTrue()
            ->and($reservation->reserved_to->greaterThan($source->ends_at))->toBeTrue();
    }
});
