<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class SetPrimaryEventReservationAction
{
    public function handle(Event $event, PlaceReservation $reservation, User $user): PlaceReservation
    {
        return DB::transaction(function () use ($event, $reservation, $user): PlaceReservation {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->authorize($event, $user);

            $reservations = PlaceReservation::query()
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->get();
            $reservation = $reservations->firstWhere('id', $reservation->id);

            if ($reservation === null) {
                throw new AuthorizationException('A reserva não pertence a este evento.');
            }

            if ($reservation->reserved_from->gt($event->starts_at)
                || $reservation->reserved_to->lt($event->ends_at)) {
                throw ValidationException::withMessages([
                    'reservation' => 'A reserva principal deve cobrir integralmente o período do evento.',
                ]);
            }

            PlaceReservation::query()
                ->whereIn('id', $reservations->modelKeys())
                ->update(['is_primary' => false]);

            $reservation->update(['is_primary' => true]);

            return $reservation->refresh();
        });
    }

    private function authorize(Event $event, User $user): void
    {
        Gate::forUser($user)->authorize('update', $event);

        if ($event->status === EventStatusEnum::CANCELED) {
            throw new AuthorizationException('Eventos cancelados não podem ter reservas alteradas.');
        }

        if ($event->is_external) {
            throw new AuthorizationException('Eventos externos não aceitam reservas de ambientes.');
        }
    }
}
