<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use Livewire\Livewire;
use App\Models\Community;
use App\Livewire\Places\Delete;
use App\Models\PlaceReservation;

it('asks for confirmation before deleting an unreserved space', function () {
    $user      = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);

    Livewire::actingAs($user)->test(Delete::class, ['community' => $community])
        ->dispatch('delete-place', placeId: $place->id)
        ->assertDispatched('ts-ui:dialog', fn (string $event, array $data): bool => $data['options']['confirm']['text'] === 'Confirmar'
            && $data['options']['confirm']['method'] === 'delete'
            && $data['options']['cancel']['text'] === 'Cancelar');

    $this->assertModelExists($place);
});

it('deletes spaces and subspaces after confirmation', function (bool $subspace) {
    $user      = User::factory()->create(['roles' => ['secretary'], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $parent    = $subspace ? $community->places()->create(['name' => 'Salão']) : null;
    $place     = $community->places()->create(['name' => 'Cozinha', 'main_place_id' => $parent?->id]);

    Livewire::actingAs($user)->test(Delete::class, ['community' => $community])
        ->call('confirm', $place->id)->call('delete')->assertDispatched('deleted');

    $this->assertModelMissing($place);

    if ($parent !== null) {
        $this->assertModelExists($parent);
    }
})->with([false, true]);

it('shows only Fechar when reservations exist before opening or arrive before confirmation', function (bool $reservedBeforeOpening) {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);
    $event     = Event::factory()->create();
    $component = Livewire::actingAs($user)->test(Delete::class, ['community' => $community]);

    if (! $reservedBeforeOpening) {
        $component->call('confirm', $place->id);
    }
    $reservation = PlaceReservation::factory()->create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => $event->starts_at,
        'reserved_to'   => $event->ends_at,
    ]);

    if ($reservedBeforeOpening) {
        $component->call('confirm', $place->id);
    } else {
        $component->call('delete');
    }

    $component->assertNotDispatched('deleted')
        ->assertDispatched('ts-ui:dialog', fn (string $event, array $data): bool => $data['type'] === 'warning'
            && $data['description'] === 'Este espaço possui reservas vinculadas. Remova ou transfira essas reservas antes de tentar excluí-lo novamente.'
            && ! isset($data['options']['confirm'])
            && $data['options']['cancel']['text'] === 'Fechar');
    $this->assertModelExists($place);
    $this->assertModelExists($reservation);
})->with([true, false]);

it('forbids unauthorized users from opening the confirmation', function () {
    $user      = User::factory()->create(['roles' => ['member'], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);

    Livewire::actingAs($user)->test(Delete::class, ['community' => $community])
        ->call('confirm', $place->id)->assertForbidden();

    $this->assertModelExists($place);
});

it('rechecks permission before deletion', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);
    $component = Livewire::actingAs($user)->test(Delete::class, ['community' => $community])
        ->call('confirm', $place->id);
    $user->update(['roles' => ['member']]);

    $component->call('delete')->assertForbidden();

    $this->assertModelExists($place);
});

it('refuses spaces from another community', function () {
    $user      = User::factory()->create(['roles' => ['admin'], 'is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $other     = Community::create(['name' => 'Capela', 'alias' => 'Capela', 'abbreviation' => 'CP']);
    $place     = $other->places()->create(['name' => 'Salão']);

    Livewire::actingAs($user)->test(Delete::class, ['community' => $community])
        ->call('confirm', $place->id)->assertNotFound();

    $this->assertModelExists($place);
});
