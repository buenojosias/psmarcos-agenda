<?php

declare(strict_types=1);

namespace App\Livewire\Events\Edit;

use App\Models\Event;
use App\Models\Place;
use Livewire\Component;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use TallStackUi\Traits\Interactions;
use App\Actions\CreateEventReservationAction;
use App\Actions\DeleteEventReservationAction;
use App\Actions\UpdateEventReservationAction;
use App\Actions\SetPrimaryEventReservationAction;

class Reservations extends Component
{
    use Interactions;

    public Event $event;

    public bool $reservationModal = false;

    public ?int $editingReservationId = null;

    public mixed $place_id = null;

    public string $reserved_from = '';

    public string $reserved_to = '';

    /** @var list<array{index: string, label: string, sortable: bool, align?: string}> */
    public array $headers = [
        ['index' => 'place.name', 'label' => 'Ambiente', 'sortable' => false],
        ['index' => 'reserved_from', 'label' => 'Início', 'sortable' => false],
        ['index' => 'reserved_to', 'label' => 'Fim', 'sortable' => false],
        ['index' => 'is_primary', 'label' => 'Principal', 'sortable' => false, 'align' => 'center'],
        ['index' => 'action', 'label' => 'Ações', 'sortable' => false, 'align' => 'right'],
    ];

    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);
        abort_if($event->status === EventStatusEnum::CANCELED || $event->is_external, 403);

        $this->event = $event;
    }

    public function openCreate(): void
    {
        $this->resetReservationForm();
        $this->reserved_from    = $this->event->starts_at->format('Y-m-d\TH:i');
        $this->reserved_to      = $this->event->ends_at->format('Y-m-d\TH:i');
        $this->reservationModal = true;
    }

    public function openEdit(int $reservationId): void
    {
        $reservation = PlaceReservation::query()
            ->where('event_id', $this->event->id)
            ->findOrFail($reservationId);

        $this->resetValidation();
        $this->editingReservationId = $reservation->id;
        $this->place_id             = $reservation->place_id;
        $this->reserved_from        = $reservation->reserved_from->format('Y-m-d\TH:i');
        $this->reserved_to          = $reservation->reserved_to->format('Y-m-d\TH:i');
        $this->reservationModal     = true;
    }

    public function saveReservation(
        CreateEventReservationAction $createReservation,
        UpdateEventReservationAction $updateReservation,
    ): void {
        $validated = $this->validate();
        $user      = user();
        abort_if($user === null, 401);

        if ($this->editingReservationId === null) {
            $createReservation->handle($this->event, $validated, $user);
            $message = 'A reserva foi adicionada.';
        } else {
            $reservation = PlaceReservation::query()
                ->where('event_id', $this->event->id)
                ->findOrFail($this->editingReservationId);
            $updateReservation->handle($this->event, $reservation, $validated, $user);
            $message = 'A reserva foi atualizada.';
        }

        $this->reservationModal = false;
        $this->resetReservationForm();
        $this->toast()->success('Reserva salva', $message)->send();
    }

    public function deleteReservation(
        int $reservationId,
        DeleteEventReservationAction $deleteReservation,
    ): void {
        $user = user();
        abort_if($user === null, 401);

        $reservation = PlaceReservation::query()
            ->where('event_id', $this->event->id)
            ->findOrFail($reservationId);
        $deleteReservation->handle($this->event, $reservation, $user);

        $this->resetValidation();
        $this->toast()->success('Reserva excluída', 'A reserva foi removida do evento.')->send();
    }

    public function setPrimary(
        int $reservationId,
        SetPrimaryEventReservationAction $setPrimaryReservation,
    ): void {
        $user = user();
        abort_if($user === null, 401);

        $reservation = PlaceReservation::query()
            ->where('event_id', $this->event->id)
            ->findOrFail($reservationId);
        $setPrimaryReservation->handle($this->event, $reservation, $user);

        $this->resetValidation();
        $this->toast()->success('Reserva principal alterada', 'A nova reserva principal foi definida.')->send();
    }

    public function render(): View
    {
        return view('livewire.events.edit.reservations', [
            'places' => Place::query()
                ->where('community_id', $this->event->community_id)
                ->orderBy('name')
                ->get(),
            'reservations' => PlaceReservation::query()
                ->where('event_id', $this->event->id)
                ->with('place')
                ->orderByDesc('is_primary')
                ->orderBy('reserved_from')
                ->orderBy('id')
                ->get(),
        ]);
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'place_id'      => ['required', 'integer'],
            'reserved_from' => ['required', 'date'],
            'reserved_to'   => ['required', 'date', 'after:reserved_from'],
        ];
    }

    private function resetReservationForm(): void
    {
        $this->resetValidation();
        $this->editingReservationId = null;
        $this->place_id             = null;
        $this->reserved_from        = '';
        $this->reserved_to          = '';
    }
}
