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
use App\Livewire\Events\Edit\Details;
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

it('loads the current event details', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['advertisable' => false]);
    $event->detail()->create([
        'subtitle'                   => 'Complemento atual',
        'description'                => '<p>Descrição atual</p>',
        'target_audience'            => 'Famílias',
        'participation_instructions' => 'Levar documento',
        'registration_required'      => true,
        'registration_url'           => 'https://example.com/inscricao',
        'registration_deadline'      => '2026-10-15',
        'participation_cost'         => 'R$ 20,00',
        'contact_name'               => 'Maria',
        'contact_phone'              => '(11) 99999-9999',
    ]);

    Livewire::actingAs($user)
        ->test(Details::class, ['event' => $event])
        ->assertSet('subtitle', 'Complemento atual')
        ->assertSet('description', '<p>Descrição atual</p>')
        ->assertSet('target_audience', 'Famílias')
        ->assertSet('participation_instructions', 'Levar documento')
        ->assertSet('registration_required', true)
        ->assertSet('registration_url', 'https://example.com/inscricao')
        ->assertSet('registration_deadline', '2026-10-15')
        ->assertSet('participation_cost', 'R$ 20,00')
        ->assertSet('contact_name', 'Maria')
        ->assertSet('contact_phone', '(11) 99999-9999')
        ->assertSee('Detalhes do evento');
});

it('starts with an empty details form when the event has no details', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['advertisable' => false]);

    Livewire::actingAs($user)
        ->test(Details::class, ['event' => $event])
        ->assertSet('subtitle', null)
        ->assertSet('description', null)
        ->assertSet('registration_required', false)
        ->assertSee('Público-alvo')
        ->assertSee('Salvar detalhes');
});

it('allows an authorized user to save event details', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(Details::class, ['event' => $event])
        ->set('subtitle', 'Encontro das famílias')
        ->set('registration_required', true)
        ->set('registration_url', 'https://example.com/inscricao')
        ->set('registration_deadline', '2026-10-15')
        ->call('save')
        ->assertHasNoErrors();

    $detail = $event->detail()->firstOrFail();

    expect($detail->subtitle)->toBe('Encontro das famílias')
        ->and($detail->registration_required)->toBeTrue()
        ->and($detail->registration_url)->toBe('https://example.com/inscricao')
        ->and($detail->registration_deadline?->format('Y-m-d'))->toBe('2026-10-15')
        ->and($event->logs()->count())->toBe(1);
});

it('does not save invalid event details', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(Details::class, ['event' => $event])
        ->set('registration_required', true)
        ->set('registration_url', 'endereco-invalido')
        ->set('registration_deadline', '15/10/2026')
        ->call('save')
        ->assertHasErrors([
            'registration_url'      => 'url',
            'registration_deadline' => 'date_format',
        ]);

    expect($event->detail()->exists())->toBeFalse()
        ->and($event->logs()->exists())->toBeFalse();
});
