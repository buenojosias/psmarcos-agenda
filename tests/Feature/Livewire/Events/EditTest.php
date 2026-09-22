<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use Livewire\Livewire;
use App\Models\Community;
use App\Enums\EventTypeEnum;
use App\Livewire\Events\Edit;
use App\Livewire\Events\Show;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\Gate;
use App\Livewire\Events\Edit\Details;
use App\Livewire\Events\Edit\General;
use App\Livewire\Events\Edit\Reschedule;
use App\Livewire\Events\Edit\Reservations;
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
        ->assertSee('Nova data e horário')
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
    $event = Event::factory()->create(['status' => EventStatusEnum::CONFIRMED]);

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

function editReservationPlace(string $name = 'Salão'): Place
{
    $community = Community::create([
        'name'         => 'Comunidade São José',
        'alias'        => fake()->unique()->slug(),
        'abbreviation' => fake()->unique()->lexify('???'),
    ]);

    return $community->places()->create(['name' => $name]);
}

it('lists event reservations with their individual actions', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 10:00:00',
        'ends_at'     => '2026-10-10 11:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ]);
    $place = editReservationPlace('Salão paroquial');
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
        'is_primary'    => true,
    ]);

    Livewire::actingAs($user)
        ->test(Reservations::class, ['event' => $event])
        ->assertSee('Salão paroquial')
        ->assertSee('Comunidade São José')
        ->assertSee('10/10/2026 09:00')
        ->assertSee('Principal')
        ->assertSee('Editar')
        ->assertSee('Excluir')
        ->assertSee('Adicionar reserva');
});

it('creates an event reservation immediately from the modal form', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 10:00:00',
        'ends_at'     => '2026-10-10 11:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ]);
    $place = editReservationPlace();

    Livewire::actingAs($user)
        ->test(Reservations::class, ['event' => $event])
        ->call('openCreate')
        ->set('place_id', $place->id)
        ->set('reserved_from', '2026-10-09T18:00')
        ->set('reserved_to', '2026-10-10T12:00')
        ->call('saveReservation')
        ->assertHasNoErrors()
        ->assertSet('reservationModal', false)
        ->assertSee('Principal');

    $reservation = $event->reservations()->sole();

    expect($reservation->place_id)->toBe($place->id)
        ->and($reservation->is_primary)->toBeTrue();
});

it('shows a friendly availability error without persisting the reservation', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 10:00:00',
        'ends_at'     => '2026-10-10 11:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ]);
    $otherEvent = Event::factory()->create();
    $primary    = editReservationPlace('Igreja');
    $conflicted = editReservationPlace('Auditório');
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $primary->id,
        'reserved_from' => '2026-10-10 09:00:00',
        'reserved_to'   => '2026-10-10 12:00:00',
        'is_primary'    => true,
    ]);
    PlaceReservation::create([
        'event_id'      => $otherEvent->id,
        'place_id'      => $conflicted->id,
        'reserved_from' => '2026-10-10 10:30:00',
        'reserved_to'   => '2026-10-10 11:30:00',
        'is_primary'    => true,
    ]);

    Livewire::actingAs($user)
        ->test(Reservations::class, ['event' => $event])
        ->set('place_id', $conflicted->id)
        ->set('reserved_from', '2026-10-10T10:00')
        ->set('reserved_to', '2026-10-10T12:00')
        ->call('saveReservation')
        ->assertHasErrors('place_id')
        ->assertSee('Auditório')
        ->assertSee('2026-10-10 10:30:00');

    expect($event->reservations()->count())->toBe(1);
});

it('proposes a new date preserving reservation offsets including a previous day', function () {
    Gate::before(static fn (): bool => true);
    $user      = User::factory()->create();
    $community = Community::create([
        'name'         => 'Comunidade dos offsets',
        'alias'        => 'offsets-date',
        'abbreviation' => 'OFD',
    ]);
    $primary = $community->places()->create(['name' => 'Igreja']);
    $support = $community->places()->create(['name' => 'Sala de apoio']);
    $event   = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => false,
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $primary->id,
        'reserved_from' => '2026-10-10 17:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $support->id,
        'reserved_from' => '2026-10-09 18:00:00',
        'reserved_to'   => '2026-10-10 18:30:00',
        'is_primary'    => false,
    ]);

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('date', '2026-10-17')
        ->assertSet('reservations.0.reserved_from', '2026-10-17T17:00')
        ->assertSet('reservations.0.reserved_to', '2026-10-17T22:00')
        ->assertSet('reservations.1.reserved_from', '2026-10-16T18:00')
        ->assertSet('reservations.1.reserved_to', '2026-10-17T18:30');

    expect($event->refresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-10 19:00:00')
        ->and($event->reservations()->count())->toBe(2);
});

it('proposes new reservation intervals preserving start and end offsets', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $place = editReservationPlace('Salão de horários');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => false,
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 17:30:00',
        'reserved_to'   => '2026-10-10 22:15:00',
        'is_primary'    => true,
    ]);

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('starts_at', '20:00')
        ->set('ends_at', '23:00')
        ->assertSet('reservations.0.reserved_from', '2026-10-10T18:30')
        ->assertSet('reservations.0.reserved_to', '2026-10-11T00:15');
});

it('requires a manual proposal after changing community and keeps old reservations untouched', function () {
    Gate::before(static fn (): bool => true);
    $user     = User::factory()->create();
    $oldPlace = editReservationPlace('Salão atual');
    $event    = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => false,
    ]);
    $oldReservation = PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $oldPlace->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    $newCommunity = Community::create([
        'name'         => 'Nova comunidade',
        'alias'        => 'new-community-reschedule',
        'abbreviation' => 'NCR',
    ]);

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('community_id', $newCommunity->id)
        ->assertSet('reservations', [])
        ->assertSee('Selecione manualmente as novas reservas')
        ->call('preview')
        ->assertHasErrors('reservations');

    $this->assertModelExists($oldReservation);
    expect($event->reservations()->count())->toBe(1);
});

it('renders a preview without persisting the proposed changes', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $place = editReservationPlace('Salão de preview');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => false,
    ]);
    $reservation = PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('date', '2026-10-17')
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSet('previewReady', true)
        ->assertSet('canConfirm', true)
        ->assertSee('Preview da remarcação')
        ->assertSee('17/10/2026 18:00');

    expect($event->refresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-10 19:00:00')
        ->and($reservation->refresh()->reserved_from->format('Y-m-d H:i:s'))->toBe('2026-10-10 18:00:00');
});

it('does not confirm when a new conflict appears after the preview', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $place = editReservationPlace('Salão disputado');
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'status'      => EventStatusEnum::CONFIRMED,
        'is_external' => false,
    ]);
    $oldReservation = PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    $component = Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('date', '2026-10-17')
        ->call('preview')
        ->assertSet('canConfirm', true);
    $otherEvent = Event::factory()->create();
    PlaceReservation::create([
        'event_id'      => $otherEvent->id,
        'place_id'      => $place->id,
        'reserved_from' => '2026-10-17 18:30:00',
        'reserved_to'   => '2026-10-17 20:30:00',
        'is_primary'    => true,
    ]);

    $component
        ->call('confirm')
        ->assertHasErrors('reservations')
        ->assertSet('canConfirm', false)
        ->assertSee('Com conflito');

    expect($event->refresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-10 19:00:00')
        ->and($event->status)->toBe(EventStatusEnum::CONFIRMED);
    $this->assertModelExists($oldReservation);
});

it('updates an external event through the reschedule component without reservations', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => true,
    ]);

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('date', '2026-10-20')
        ->set('starts_at', '20:00')
        ->set('ends_at', '22:00')
        ->set('external_location_name', 'Centro cultural')
        ->set('external_location_address', 'Rua Nova, 200')
        ->set('external_location_url', 'https://example.com/local')
        ->call('preview')
        ->assertHasNoErrors()
        ->call('confirm')
        ->assertHasNoErrors();

    $detail = $event->refresh()->detail()->firstOrFail();

    expect($event->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-20 20:00:00')
        ->and($event->reservations()->exists())->toBeFalse()
        ->and($detail->external_location_name)->toBe('Centro cultural')
        ->and($detail->external_location_address)->toBe('Rua Nova, 200');
});

it('forbids an unauthorized user from opening the reschedule component', function () {
    Gate::before(static fn (): bool => false);
    $user  = User::factory()->create();
    $event = Event::factory()->create();

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->assertForbidden();
});
