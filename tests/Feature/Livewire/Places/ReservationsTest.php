<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventStatusEnum;
use App\Livewire\Places\Reservations;

it('shows the parent name before a subspace name', function (bool $hasParent) {
    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $parent    = $hasParent ? $community->places()->create(['name' => 'Centro Catequético']) : null;
    $place     = $community->places()->create(['name' => 'Sala 1', 'main_place_id' => $parent?->id]);

    Livewire::actingAs(User::factory()->create())
        ->test(Reservations::class, ['place' => $place])
        ->assertSee('Reservas de '.($hasParent ? 'Centro Catequético: ' : '').'Sala 1');
})->with([true, false]);

it('filters reservations by every day they occupy and restores the list when cleared', function () {
    $this->travelTo('2026-10-01 08:00:00');

    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);
    $spanning  = Event::factory()->create(['name' => 'Vigília', 'status' => EventStatusEnum::CONFIRMED]);
    $later     = Event::factory()->create(['name' => 'Encontro', 'status' => EventStatusEnum::CONFIRMED]);
    $spanning->reservations()->create(['place_id' => $place->id, 'reserved_from' => '2026-10-02 23:00:00', 'reserved_to' => '2026-10-03 01:00:00']);
    $later->reservations()->create(['place_id' => $place->id, 'reserved_from' => '2026-10-04 10:00:00', 'reserved_to' => '2026-10-04 12:00:00']);

    Livewire::actingAs(User::factory()->create())
        ->test(Reservations::class, ['place' => $place])
        ->assertSee('Buscar por data')
        ->assertSee('Vigília')
        ->assertSee('Encontro')
        ->set('date', '2026-10-03')
        ->assertSee('Vigília')
        ->assertDontSee('Encontro')
        ->set('date', null)
        ->assertSee('Vigília')
        ->assertSee('Encontro')
        ->set('date', '2026-10-99')
        ->assertSet('date', null)
        ->assertSee('Vigília')
        ->assertSee('Encontro');
});

it('shows 15 reservations per page and returns to the first page when the date changes', function () {
    $this->travelTo('2026-10-01 08:00:00');

    $community = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);

    for ($number = 1; $number <= 16; $number++) {
        $event = Event::factory()->create([
            'name'   => sprintf('Reserva %02d', $number),
            'status' => EventStatusEnum::CONFIRMED,
        ]);
        $date = $number === 16 ? '2026-10-04' : '2026-10-03';

        $event->reservations()->create([
            'place_id'      => $place->id,
            'reserved_from' => $date.' 10:00:00',
            'reserved_to'   => $date.' 11:00:00',
        ]);
    }

    Livewire::actingAs(User::factory()->create())
        ->test(Reservations::class, ['place' => $place])
        ->assertSee('Reserva 01')
        ->assertSee('Reserva 15')
        ->assertDontSee('Reserva 16')
        ->call('nextPage')
        ->assertSee('Reserva 16')
        ->assertDontSee('Reserva 01')
        ->set('date', '2026-10-03')
        ->assertSee('Reserva 01')
        ->assertDontSee('Reserva 16');
});
