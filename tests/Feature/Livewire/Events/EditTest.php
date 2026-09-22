<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use Livewire\Livewire;
use App\Enums\EventTypeEnum;
use App\Livewire\Events\Edit;
use App\Livewire\Events\Show;
use App\Enums\EventStatusEnum;
use Illuminate\Support\Facades\Gate;
use App\Livewire\Events\Edit\General;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an authorized user to access the edit page', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $event])
        ->assertSee('Editar evento')
        ->assertSee('Nome do evento');
});

it('forbids an unauthorized user from accessing the edit page', function () {
    Gate::before(static fn (): bool => false);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $event])
        ->assertForbidden();
});

it('forbids editing a canceled event even when the user is authorized', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['status' => EventStatusEnum::CANCELED]);

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $event])
        ->assertForbidden();
});

it('persists the selected tab in the query string', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->withQueryParams(['tab' => 'reschedule'])
        ->test(Edit::class, ['event' => $event])
        ->assertSet('tab', 'reschedule')
        ->assertSee('Seção de remarcação do evento.')
        ->assertDontSee('Seção de informações do evento.');
});

it('does not show the reservations tab for an external event', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['is_external' => true]);

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $event])
        ->assertDontSee('Reservas');
});

it('loads only the current occurrence of a recurring event', function () {
    Gate::before(static fn (): bool => true);
    $user              = User::factory()->create();
    $currentOccurrence = Event::factory()->create(['recurrence_code' => 'weekly-event']);
    Event::factory()->create(['recurrence_code' => 'weekly-event']);

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $currentOccurrence])
        ->assertSet('event.id', $currentOccurrence->id)
        ->assertDontSee('Solicitar divulgação');
});

it('links the reschedule button to the reschedule edit tab', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(Show::class, ['event' => $event])
        ->assertSeeHtml('href="'.route('events.edit', ['event' => $event, 'tab' => 'reschedule']).'"');
});

it('loads the current general information', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'         => 'Encontro atual',
        'type'         => EventTypeEnum::MEETING,
        'is_public'    => true,
        'advertisable' => true,
    ]);

    Livewire::actingAs($user)
        ->test(General::class, ['event' => $event])
        ->assertSet('name', 'Encontro atual')
        ->assertSet('type', EventTypeEnum::MEETING->value)
        ->assertSet('is_public', true)
        ->assertSet('advertisable', true);
});

it('allows an authorized user to edit general information', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['name' => 'Nome anterior']);

    Livewire::actingAs($user)
        ->test(General::class, ['event' => $event])
        ->set('name', 'Nome atualizado')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->name)->toBe('Nome atualizado');
});
