<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Community;
use App\Models\PlaceReservation;
use Database\Seeders\EventSeeder;

it('seeds internal events with reservations only in their own community', function () {
    $first  = Community::create(['name' => 'Matriz', 'alias' => 'matriz', 'abbreviation' => 'MT']);
    $second = Community::create(['name' => 'Capela', 'alias' => 'capela', 'abbreviation' => 'CP']);
    Community::create(['name' => 'Sem espaço', 'alias' => 'sem-espaco', 'abbreviation' => 'SE']);
    $first->places()->createMany([['name' => 'Salão'], ['name' => 'Nave']]);
    $second->places()->createMany([['name' => 'Pátio'], ['name' => 'Sala']]);
    User::factory()->create();
    Group::factory()->create();

    app(EventSeeder::class)->run();

    $events = Event::query()->with('reservations.place')->get();
    expect($events)->toHaveCount(16);
    expect($events->whereNotNull('recurrence_code'))->toHaveCount(8);
    expect($events->whereNull('recurrence_code'))->toHaveCount(8);
    expect($events->filter(fn (Event $event): bool => filled($event->complement)))->toHaveCount(8);
    expect($events->flatMap(fn (Event $event) => $event->reservations)->every(
        fn (PlaceReservation $reservation): bool => $reservation->reserved_from->minute % 15 === 0
            && $reservation->reserved_to->minute % 15 === 0
            && $reservation->reserved_from->second === 0
            && $reservation->reserved_to->second === 0
    ))->toBeTrue();
    expect($events->every(fn (Event $event): bool => ! $event->is_external
        && in_array($event->community_id, [$first->id, $second->id], true)
        && $event->reservations->where('is_primary', true)->count() === 1
        && $event->reservations->every(fn ($reservation): bool => $reservation->place->community_id === $event->community_id)))->toBeTrue();
    expect($events->whereNotNull('recurrence_code')->groupBy('recurrence_code')->every(
        fn ($series): bool => $series->pluck('community_id')->unique()->count() === 1
    ))->toBeTrue();

    foreach ([
        'Jantar dançante'           => 'Noite de confraternização',
        'Ensaio geral do coral'     => 'Preparação para a celebração',
        'Festa da comunidade'       => 'Confraternização paroquial',
        'Encontro de coordenadores' => 'Planejamento das atividades',
    ] as $name => $complement) {
        $this->assertDatabaseHas('events', ['name' => $name, 'complement' => $complement]);
    }
});
