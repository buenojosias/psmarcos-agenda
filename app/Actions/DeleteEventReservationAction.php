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

class DeleteEventReservationAction
{
    public function handle(Event $event, PlaceReservation $reservation, User $user): void
    {
        DB::transaction(function () use ($event, $reservation, $user): void {
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

            if ($reservations->count() === 1) {
                throw ValidationException::withMessages([
                    'reservation' => 'O evento interno deve possuir ao menos uma reserva.',
                ]);
            }

            if ($reservation->is_primary) {
                throw ValidationException::withMessages([
                    'reservation' => 'Defina outra reserva como principal antes de excluir esta reserva.',
                ]);
            }

            $reservation->delete();
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
