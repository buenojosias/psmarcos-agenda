<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use Carbon\CarbonImmutable;
use App\Enums\EventStatusEnum;
use Illuminate\Validation\Rule;
use App\Models\PlaceReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateEventReservationAction
{
    public function __construct(private CheckPlaceAvailabilityAction $checkPlaceAvailability) {}

    /** @param array<string, mixed> $data */
    public function handle(Event $event, PlaceReservation $reservation, array $data, User $user): PlaceReservation
    {
        return DB::transaction(function () use ($event, $reservation, $data, $user): PlaceReservation {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->authorize($event, $user);

            $reservation = PlaceReservation::query()->lockForUpdate()->findOrFail($reservation->id);

            if ($reservation->event_id !== $event->id) {
                throw new AuthorizationException('A reserva não pertence a este evento.');
            }

            $validated = validator($data, [
                'place_id'      => ['required', 'integer', Rule::exists(Place::class, 'id')],
                'reserved_from' => ['required', 'date'],
                'reserved_to'   => ['required', 'date', 'after:reserved_from'],
            ])->validate();

            $place        = Place::query()->findOrFail($validated['place_id']);
            $reservedFrom = CarbonImmutable::parse($validated['reserved_from']);
            $reservedTo   = CarbonImmutable::parse($validated['reserved_to']);

            if ($reservation->is_primary && ! $this->coversEvent($event, $reservedFrom, $reservedTo)) {
                throw ValidationException::withMessages([
                    'reserved_from' => 'A reserva principal deve cobrir integralmente o período do evento.',
                ]);
            }

            $this->ensureAvailable($place, $reservedFrom, $reservedTo, $reservation->id);

            $reservation->update([
                'place_id'      => $place->id,
                'reserved_from' => $reservedFrom,
                'reserved_to'   => $reservedTo,
            ]);

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

    private function coversEvent(Event $event, CarbonImmutable $reservedFrom, CarbonImmutable $reservedTo): bool
    {
        return $reservedFrom->lte($event->starts_at) && $reservedTo->gte($event->ends_at);
    }

    private function ensureAvailable(
        Place $place,
        CarbonImmutable $reservedFrom,
        CarbonImmutable $reservedTo,
        int $ignoredReservationId,
    ): void {
        $conflicts = $this->checkPlaceAvailability->handle(
            $reservedFrom,
            $reservedTo,
            new Collection([$place]),
            [$place->id => ['before_hours' => 0, 'after_hours' => 0]],
            [$ignoredReservationId],
        );

        if ($conflicts === []) {
            return;
        }

        $conflict = $conflicts[0]['conflicts'][0];

        throw ValidationException::withMessages([
            'place_id' => sprintf(
                'O ambiente %s está indisponível entre %s e %s.',
                $conflict['reserved_place']['name'],
                $conflict['reserved_from'],
                $conflict['reserved_to'],
            ),
        ]);
    }
}
