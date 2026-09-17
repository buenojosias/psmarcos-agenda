<?php

declare(strict_types=1);

use App\Models\User;
use Livewire\Livewire;
use App\Models\Community;
use Illuminate\Support\Facades\DB;
use App\Livewire\Communities\Places;
use Illuminate\Database\Events\QueryExecuted;

it('loads only community roots and their direct children in two queries', function () {
    $user      = User::factory()->create(['is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $other     = Community::create(['name' => 'Capela', 'alias' => 'Capela', 'abbreviation' => 'CP']);
    $hall      = $community->places()->create(['name' => 'Salão']);
    $church    = $community->places()->create(['name' => 'Igreja']);
    $community->places()->create(['name' => 'Sacristia', 'main_place_id' => $church->id]);
    $kitchen = $community->places()->create(['name' => 'Cozinha', 'main_place_id' => $hall->id]);
    $community->places()->create(['name' => 'Depósito', 'main_place_id' => $hall->id]);
    $community->places()->create(['name' => 'Neto oculto', 'main_place_id' => $kitchen->id]);
    $other->places()->create(['name' => 'Outro ambiente']);
    $other->places()->create(['name' => 'Filho de outra comunidade', 'main_place_id' => $hall->id]);
    $queries = 0;
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        if (preg_match('/from ["`]?places["`]?/i', $query->sql)) {
            $queries++;
        }
    });

    Livewire::actingAs($user)->withoutLazyLoading()->test(Places::class, ['community' => $community])
        ->assertSeeInOrder(['Igreja', 'Sacristia', 'Salão', 'Cozinha', 'Depósito'])
        ->assertDontSee('Neto oculto')->assertDontSee('Outro ambiente')
        ->assertDontSee('Filho de outra comunidade')
        ->assertViewHas('places', fn ($places): bool => $places->pluck('id')->all() === [$church->id, $hall->id]);

    expect($queries)->toBe(2);
});

it('shows a friendly empty state', function () {
    $user      = User::factory()->create(['is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    Livewire::actingAs($user)->withoutLazyLoading()->test(Places::class, ['community' => $community])
        ->assertSee('Nenhum espaço está cadastrado para esta comunidade.');
});

it('authorizes the lazy request using the existing community policy', function () {
    $user      = User::factory()->create(['is_active' => false]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);

    Livewire::actingAs($user)->withoutLazyLoading()->test(Places::class, ['community' => $community])
        ->assertForbidden();
});

it('starts cards minimized only for places with children', function (bool $hasChildren) {
    $user      = User::factory()->create(['is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Salão']);

    if ($hasChildren) {
        $community->places()->create(['name' => 'Cozinha', 'main_place_id' => $place->id]);
    }

    $component = Livewire::actingAs($user)->withoutLazyLoading()->test(Places::class, ['community' => $community])
        ->assertSee('Salão');

    if ($hasChildren) {
        $component->assertSeeHtml('tallstackui_card(true)')
            ->assertSeeHtml('dusk="tallstackui_card_minimize"')->assertSee('Cozinha');
    } else {
        $component->assertDontSeeHtml('dusk="tallstackui_card_minimize"')
            ->assertSeeHtml('tallstackui_card(false)');
    }
})->with([true, false]);

it('shows creation controls only to permitted users', function (bool $allowed) {
    $user = User::factory()->create([
        'roles'     => [$allowed ? 'secretary' : 'member'],
        'is_active' => true,
    ]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $community->places()->create(['name' => 'Salão']);

    $component = Livewire::actingAs($user)->withoutLazyLoading()
        ->test(Places::class, ['community' => $community]);

    if ($allowed) {
        $component->assertSee('Adicionar espaço')->assertSee('Adicionar subespaço')
            ->assertSeeLivewire(App\Livewire\Places\Create::class);
    } else {
        $component->assertDontSee('Adicionar espaço')->assertDontSee('Adicionar subespaço')
            ->assertDontSeeLivewire(App\Livewire\Places\Create::class);
    }
})->with([true, false]);

it('refreshes space names after an update', function () {
    $user      = User::factory()->create(['is_active' => true]);
    $community = Community::create(['name' => 'Matriz', 'alias' => 'Matriz', 'abbreviation' => 'MT']);
    $place     = $community->places()->create(['name' => 'Nome anterior']);
    $component = Livewire::actingAs($user)->withoutLazyLoading()
        ->test(Places::class, ['community' => $community])->assertSee('Nome anterior');
    $place->update(['name' => 'Nome atualizado']);

    $component->dispatch('place-updated')->assertSee('Nome atualizado')->assertDontSee('Nome anterior');
});
