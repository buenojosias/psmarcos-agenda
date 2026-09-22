<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use Carbon\CarbonImmutable;
use App\Enums\EventStatusEnum;
use App\Models\PlaceReservation;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class ResubmitRefusedEventAction
{
    public function __construct(
        private CheckPlaceAvailabilityAction $checkPlaceAvailability,
        private EventLogAction $eventLog,
    ) {}

    public function handle(Event $event): Event
    {
        return DB::transaction(function () use ($event): Event {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());

            Gate::authorize('update', $event);

            if ($event->status === EventStatusEnum::CANCELED) {
                throw new AuthorizationException('Eventos cancelados não podem ser reenviados para aprovação.');
            }

            if ($event->status !== EventStatusEnum::REFUSED) {
                throw ValidationException::withMessages([
                    'resubmit' => 'Somente eventos recusados podem ser reenviados para aprovação.',
                ]);
            }

            $user = Auth::user();

            if (! $user instanceof User) {
                throw new AuthorizationException('É necessário estar autenticado para reenviar o evento.');
            }

            $this->validateMinimumConsistency($event);

            if ($event->is_external) {
                $this->validateExternalLocation($event);
            } else {
                $this->validateReservations($event);
            }

            $holdUntil = $event->reservation_hold_until?->format('Y-m-d H:i:s');

            $event->forceFill([
                'status'                 => EventStatusEnum::PENDING,
                'reservation_hold_until' => null,
            ])->save();

            $this->eventLog->handle(
                event: $event,
                action: EventLogActionEnum::SUBMITTED,
                user: $user,
                fromStatus: EventStatusEnum::REFUSED,
                toStatus: EventStatusEnum::PENDING,
                changes: [
                    'reservation_hold_until' => ['from' => $holdUntil, 'to' => null],
                ],
            );

            return $event->refresh();
        });
    }

    private function validateMinimumConsistency(Event $event): void
    {
        Validator::make([
            'name'      => $event->name,
            'type'      => $event->type,
            'starts_at' => $event->starts_at,
            'ends_at'   => $event->ends_at,
        ], [
            'name'      => ['required', 'string', 'max:255'],
            'type'      => ['required'],
            'starts_at' => ['required', 'date'],
            'ends_at'   => ['required', 'date', 'after:starts_at'],
        ], [
            'name.required' => 'Informe o nome do evento antes de reenviá-lo.',
            'type.required' => 'Informe o tipo do evento antes de reenviá-lo.',
            'ends_at.after' => 'O término do evento deve ser posterior ao início.',
        ])->validate();
    }

    private function validateExternalLocation(Event $event): void
    {
        $detail = $event->detail()->first();

        Validator::make([
            'external_location_name'    => $detail?->external_location_name,
            'external_location_address' => $detail?->external_location_address,
        ], [
            'external_location_name'    => ['required', 'string', 'max:255'],
            'external_location_address' => ['required', 'string', 'max:255'],
        ], [
            'external_location_name.required'    => 'Informe o nome do local externo antes do reenvio.',
            'external_location_address.required' => 'Informe o endereço do local externo antes do reenvio.',
        ])->validate();
    }

    private function validateReservations(Event $event): void
    {
        $reservations = PlaceReservation::query()
            ->where('event_id', $event->id)
            ->lockForUpdate()
            ->orderBy('id')
            ->get();

        if ($reservations->isEmpty()) {
            throw ValidationException::withMessages([
                'reservations' => 'Adicione ao menos uma reserva antes de reenviar o evento para aprovação.',
            ]);
        }

        $primaryReservations = $reservations->where('is_primary', true);

        if ($primaryReservations->count() !== 1) {
            throw ValidationException::withMessages([
                'reservations' => 'O evento deve possuir exatamente uma reserva principal antes do reenvio.',
            ]);
        }

        $primary = $primaryReservations->first();

        if ($primary->reserved_from->gt($event->starts_at)
            || $primary->reserved_to->lt($event->ends_at)) {
            throw ValidationException::withMessages([
                'reservations' => 'A reserva principal deve cobrir integralmente o horário do evento.',
            ]);
        }

        $invalidReservation = $reservations->first(
            fn (PlaceReservation $reservation): bool => $reservation->reserved_to->lte($reservation->reserved_from),
        );

        if ($invalidReservation !== null) {
            throw ValidationException::withMessages([
                'reservations' => 'Todas as reservas devem possuir um intervalo válido antes do reenvio.',
            ]);
        }

        $places = Place::query()->whereKey($reservations->pluck('place_id'))->get();

        if ($places->count() !== $reservations->count()) {
            throw ValidationException::withMessages([
                'reservations' => 'Todas as reservas devem possuir um ambiente válido antes do reenvio.',
            ]);
        }

        $startsAt   = CarbonImmutable::instance($event->starts_at);
        $endsAt     = CarbonImmutable::instance($event->ends_at);
        $placeHours = $reservations->mapWithKeys(function (PlaceReservation $reservation) use ($startsAt, $endsAt): array {
            return [$reservation->place_id => [
                'before_hours' => ($startsAt->getTimestamp() - $reservation->reserved_from->getTimestamp()) / 3600,
                'after_hours'  => ($reservation->reserved_to->getTimestamp() - $endsAt->getTimestamp()) / 3600,
            ]];
        })->all();
        $conflicts = $this->checkPlaceAvailability->handle(
            $startsAt,
            $endsAt,
            $places,
            $placeHours,
            $reservations->modelKeys(),
        );

        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'reservations' => 'As reservas possuem conflitos e precisam ser ajustadas antes do reenvio.',
            ]);
        }
    }
}
