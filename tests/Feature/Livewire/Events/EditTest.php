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

it('shows the latest refusal reason and reservation hold while editing a refused event', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'status'                 => EventStatusEnum::REFUSED,
        'reservation_hold_until' => '2026-10-15 23:59:00',
    ]);
    $event->notes()->create([
        'user_id' => $user->id,
        'content' => 'Motivo anterior.',
    ]);
    $event->notes()->create([
        'user_id' => $user->id,
        'content' => 'Ajuste a reserva principal.',
    ]);

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $event])
        ->assertSee('Status: Recusado')
        ->assertSee('Ajuste a reserva principal.')
        ->assertDontSee('Motivo anterior.')
        ->assertSee('15/10/2026 23:59')
        ->assertSee('O evento permanece recusado enquanto você realiza os ajustes.')
        ->assertSee('Enviar novamente para aprovação');
});

it('does not show the resubmit action for an event that is not refused', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['status' => EventStatusEnum::PENDING]);

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $event])
        ->assertDontSee('Enviar novamente para aprovação');
});

it('resubmits a refused event after confirmation and stays on the edit page', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'status'                 => EventStatusEnum::REFUSED,
        'is_external'            => true,
        'reservation_hold_until' => '2026-10-15 23:59:00',
    ]);
    $event->detail()->create([
        'external_location_name'    => 'Auditório externo',
        'external_location_address' => 'Rua Central, 100',
    ]);

    Livewire::actingAs($user)
        ->test(Edit::class, ['event' => $event])
        ->assertSet('resubmitConfirmation', false)
        ->call('openResubmitConfirmation')
        ->assertSet('resubmitConfirmation', true)
        ->assertSee('As informações e reservas serão validadas novamente.')
        ->call('resubmit')
        ->assertHasNoErrors()
        ->assertSet('resubmitConfirmation', false)
        ->assertSet('event.status', EventStatusEnum::PENDING)
        ->assertDontSee('Enviar novamente para aprovação')
        ->assertNoRedirect();

    expect($event->refresh()->status)->toBe(EventStatusEnum::PENDING)
        ->and($event->reservation_hold_until)->toBeNull();
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
        ->assertSee('Solicitar divulgação')
        ->assertSee('Ao ativar esta opção, será solicitada divulgação apenas desta ocorrência de evento.');
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
        'complement'   => 'Partilha e formação',
        'type'         => EventTypeEnum::MEETING,
        'is_public'    => true,
        'advertisable' => true,
    ]);

    Livewire::actingAs($user)
        ->test(General::class, ['event' => $event])
        ->assertSet('name', 'Encontro atual')
        ->assertSet('complement', 'Partilha e formação')
        ->assertSet('type', EventTypeEnum::MEETING->value)
        ->assertSet('is_public', true)
        ->assertSet('advertisable', true)
        ->assertSee('Solicitar divulgação')
        ->assertDontSee('Ao ativar esta opção, será solicitada divulgação apenas desta ocorrência de evento.');
});

it('allows an authorized user to edit general information', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['name' => 'Nome anterior', 'complement' => null]);

    Livewire::actingAs($user)
        ->test(General::class, ['event' => $event])
        ->set('name', 'Nome atualizado')
        ->set('complement', 'Encontro das famílias')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->name)->toBe('Nome atualizado')
        ->and($event->complement)->toBe('Encontro das famílias');
});

it('saves a recurring occurrence complement and advertising request without changing another occurrence', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'recurrence_code' => 'weekly-event',
        'advertisable'    => false,
        'complement'      => null,
    ]);
    $otherOccurrence = Event::factory()->create([
        'recurrence_code' => 'weekly-event',
        'advertisable'    => false,
        'complement'      => null,
    ]);

    Livewire::actingAs($user)
        ->test(General::class, ['event' => $event])
        ->assertSee('Ao ativar esta opção, será solicitada divulgação apenas desta ocorrência de evento.')
        ->set('complement', 'Celebração das famílias')
        ->set('advertisable', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->complement)->toBe('Celebração das famílias')
        ->and($event->advertisable)->toBeTrue()
        ->and($otherOccurrence->refresh()->complement)->toBeNull()
        ->and($otherOccurrence->advertisable)->toBeFalse();
});

it('rejects a complement longer than 255 characters', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['complement' => null]);

    Livewire::actingAs($user)
        ->test(General::class, ['event' => $event])
        ->set('complement', str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors(['complement' => 'max']);

    expect($event->refresh()->complement)->toBeNull();
});

it('keeps a refused event refused after editing general information', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create([
        'name'   => 'Nome recusado',
        'status' => EventStatusEnum::REFUSED,
    ]);

    Livewire::actingAs($user)
        ->test(General::class, ['event' => $event])
        ->set('name', 'Nome recusado atualizado')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->name)->toBe('Nome recusado atualizado')
        ->and($event->status)->toBe(EventStatusEnum::REFUSED);
});

it('loads the current event details', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['advertisable' => false]);
    $event->detail()->create([
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
        ->assertSet('description', '<p>Descrição atual</p>')
        ->assertSet('target_audience', 'Famílias')
        ->assertSet('participation_instructions', 'Levar documento')
        ->assertSet('registration_required', true)
        ->assertSet('registration_url', 'https://example.com/inscricao')
        ->assertSet('registration_deadline', '2026-10-15')
        ->assertSet('participation_cost', 'R$ 20,00')
        ->assertSet('contact_name', 'Maria')
        ->assertSet('contact_phone', '(11) 99999-9999')
        ->assertSee('Detalhes do evento')
        ->assertDontSee('Complemento');
});

it('starts with an empty details form when the event has no details', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $event = Event::factory()->create(['advertisable' => false]);

    Livewire::actingAs($user)
        ->test(Details::class, ['event' => $event])
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
        ->set('description', '<p>Encontro das famílias</p>')
        ->set('registration_required', true)
        ->set('registration_url', 'https://example.com/inscricao')
        ->set('registration_deadline', '2026-10-15')
        ->call('save')
        ->assertHasNoErrors();

    $detail = $event->detail()->firstOrFail();

    expect($detail->description)->toBe('<p>Encontro das famílias</p>')
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
        ->assertDontSee('Comunidade São José')
        ->assertDontSee('Comunidade')
        ->assertSee('10/10/2026 09:00')
        ->assertSee('Principal')
        ->assertSee('Editar')
        ->assertSee('Excluir')
        ->assertSee('Adicionar reserva');
});

it('offers parent and child environments from the event community without showing community names', function () {
    Gate::before(static fn (): bool => true);
    $user           = User::factory()->create();
    $place          = editReservationPlace('Salão da Matriz');
    $child          = $place->subplaces()->create(['name' => 'Sala de apoio', 'community_id' => $place->community_id]);
    $otherCommunity = Community::create([
        'name'         => 'Capela do Beato',
        'alias'        => 'beato-reservas',
        'abbreviation' => 'BRS',
    ]);
    $otherParent = $otherCommunity->places()->create(['name' => 'Sala da Capela']);
    $otherParent->subplaces()->create(['name' => 'Sala auxiliar', 'community_id' => $otherCommunity->id]);
    $event = Event::factory()->create(['community_id' => $place->community_id]);

    Livewire::actingAs($user)
        ->test(Reservations::class, ['event' => $event])
        ->call('openCreate')
        ->assertSee('Salão da Matriz')
        ->assertSee('Salão da Matriz: Sala de apoio')
        ->assertSeeHtml('value="'.$child->id.'"')
        ->assertDontSee('Sala da Capela')
        ->assertDontSee('Sala da Capela: Sala auxiliar')
        ->assertDontSee('Comunidade São José')
        ->assertDontSee('Capela do Beato')
        ->assertDontSee('Comunidade');
});

it('creates an event reservation immediately from the modal form', function () {
    Gate::before(static fn (): bool => true);
    $user  = User::factory()->create();
    $place = editReservationPlace();
    $event = Event::factory()->create([
        'community_id' => $place->community_id,
        'starts_at'    => '2026-10-10 10:00:00',
        'ends_at'      => '2026-10-10 11:00:00',
        'status'       => EventStatusEnum::CONFIRMED,
        'is_external'  => false,
    ]);
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
        ->assertSee('Verificar remarcação')
        ->assertDontSee('Reservas propostas')
        ->assertDontSee('Adicionar reserva')
        ->assertDontSee('Tornar principal')
        ->assertDontSee('Igreja')
        ->assertDontSee('Sala de apoio')
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

it('selects environments after changing community and keeps old reservations untouched', function () {
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
    $newPrimary = $newCommunity->places()->create(['name' => 'Nova igreja']);
    $newSupport = $newCommunity->places()->create(['name' => 'Nova sala de apoio']);

    $component = Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('community_id', $newCommunity->id)
        ->assertSet('reservations', [])
        ->assertSee('As reservas atuais não serão transferidas automaticamente')
        ->assertSee('Novos ambientes')
        ->assertSee('Selecione um ambiente')
        ->call('preview')
        ->assertHasErrors(['selected_place_ids', 'primary_place_id']);

    expect($component->errors()->get('selected_place_ids'))->toHaveCount(1)
        ->and($component->errors()->get('primary_place_id'))->toHaveCount(1);

    $component
        ->set('selected_place_ids', [$newPrimary->id, $newSupport->id])
        ->set('primary_place_id', $newPrimary->id)
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSet('canConfirm', true)
        ->assertSet('reservations.0.place_id', $newPrimary->id)
        ->assertSet('reservations.0.is_primary', true)
        ->assertSet('reservations.1.place_id', $newSupport->id)
        ->assertSet('reservations.1.is_primary', false);

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
        ->assertSee('Resumo da remarcação')
        ->assertSee('Ambientes que serão mantidos')
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
        ->assertSee('Conflitos encontrados')
        ->assertSee('Ambiente em conflito: Salão disputado');

    expect($event->refresh()->starts_at->format('Y-m-d H:i:s'))->toBe('2026-10-10 19:00:00')
        ->and($event->status)->toBe(EventStatusEnum::CONFIRMED);
    $this->assertModelExists($oldReservation);
});

it('shows only a conflicting auxiliary reservation and rechecks after removing it', function () {
    Gate::before(static fn (): bool => true);
    $user      = User::factory()->create();
    $community = Community::create([
        'name'         => 'Comunidade dos conflitos auxiliares',
        'alias'        => 'auxiliary-conflicts',
        'abbreviation' => 'AUX',
    ]);
    $primary    = $community->places()->create(['name' => 'Igreja principal']);
    $support    = $community->places()->create(['name' => 'Sala auxiliar']);
    $otherEvent = Event::factory()->create();
    $event      = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => false,
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $primary->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    $oldSupportReservation = PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $support->id,
        'reserved_from' => '2026-10-10 18:30:00',
        'reserved_to'   => '2026-10-10 21:30:00',
        'is_primary'    => false,
    ]);
    PlaceReservation::create([
        'event_id'      => $otherEvent->id,
        'place_id'      => $support->id,
        'reserved_from' => '2026-10-17 19:00:00',
        'reserved_to'   => '2026-10-17 20:00:00',
        'is_primary'    => true,
    ]);

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('date', '2026-10-17')
        ->call('preview')
        ->assertSet('canConfirm', false)
        ->assertSee('Sala auxiliar')
        ->assertDontSee('Igreja principal')
        ->assertSee('Remover desta remarcação')
        ->assertSee('Ajustar intervalo')
        ->assertDontSee('Novo início da reserva')
        ->call('adjustReservation', 1)
        ->assertSee('Novo início da reserva')
        ->assertSee('Novo fim da reserva')
        ->call('removeReservationFromProposal', 1)
        ->assertHasNoErrors()
        ->assertSet('conflicts', [])
        ->assertSet('canConfirm', true)
        ->assertCount('reservations', 1);

    $this->assertModelExists($oldSupportReservation);
    expect($event->reservations()->count())->toBe(2);
});

it('keeps a conflicting primary reservation and allows another environment', function () {
    Gate::before(static fn (): bool => true);
    $user      = User::factory()->create();
    $community = Community::create([
        'name'         => 'Comunidade do conflito principal',
        'alias'        => 'primary-conflict',
        'abbreviation' => 'PRI',
    ]);
    $primary     = $community->places()->create(['name' => 'Igreja ocupada']);
    $alternative = $community->places()->create(['name' => 'Salão disponível']);
    $event       = Event::factory()->create([
        'starts_at'   => '2026-10-10 19:00:00',
        'ends_at'     => '2026-10-10 21:00:00',
        'is_external' => false,
    ]);
    PlaceReservation::create([
        'event_id'      => $event->id,
        'place_id'      => $primary->id,
        'reserved_from' => '2026-10-10 18:00:00',
        'reserved_to'   => '2026-10-10 22:00:00',
        'is_primary'    => true,
    ]);
    PlaceReservation::create([
        'event_id'      => Event::factory()->create()->id,
        'place_id'      => $primary->id,
        'reserved_from' => '2026-10-17 18:30:00',
        'reserved_to'   => '2026-10-17 21:30:00',
        'is_primary'    => true,
    ]);

    Livewire::actingAs($user)
        ->test(Reschedule::class, ['event' => $event])
        ->set('date', '2026-10-17')
        ->call('preview')
        ->assertSet('canConfirm', false)
        ->assertSee('Igreja ocupada')
        ->assertSee('Selecionar outro ambiente da comunidade')
        ->assertSee('Ajustar intervalo')
        ->assertDontSee('Remover desta remarcação')
        ->set('reservations.0.place_id', $alternative->id)
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSet('conflicts', [])
        ->assertSet('canConfirm', true)
        ->assertSee('Salão disponível');

    expect($event->reservations()->sole()->place_id)->toBe($primary->id);
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
