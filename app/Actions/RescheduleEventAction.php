<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Models\Event;
use App\Models\Place;
use App\Models\EventDetail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use App\Enums\EventStatusEnum;
use Illuminate\Validation\Rule;
use App\Models\PlaceReservation;
use App\Enums\EventLogActionEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class RescheduleEventAction
{
    public function __construct(
        private CheckPlaceAvailabilityAction $checkPlaceAvailability,
        private EventLogAction $eventLog,
    ) {}

    /**
     * @param  list<array{place_id: int|string, reserved_from: string, reserved_to: string, is_primary: bool}>  $reservations
     * @param  array{external_location_name?: string|null, external_location_address?: string|null, external_location_url?: string|null}|null  $externalLocation
     */
    public function handle(
        Event $event,
        CarbonImmutable|string $startsAt,
        CarbonImmutable|string $endsAt,
        array $reservations = [],
        ?int $communityId = null,
        ?array $externalLocation = null,
    ): Event {
        return DB::transaction(function () use (
            $event,
            $startsAt,
            $endsAt,
            $reservations,
            $communityId,
            $externalLocation,
        ): Event {
            $event = Event::query()->lockForUpdate()->findOrFail($event->getKey());

            Gate::authorize('update', $event);

            if ($event->status === EventStatusEnum::CANCELED) {
                throw new AuthorizationException('Eventos cancelados não podem ser remarcados.');
            }

            $user = Auth::user();

            if (! $user instanceof User) {
                throw new AuthorizationException('É necessário estar autenticado para remarcar um evento.');
            }

            $startsAt = $this->toDateTime($startsAt);
            $endsAt   = $this->toDateTime($endsAt);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'O horário final deve ser posterior ao horário inicial.',
                ]);
            }

            $currentReservations = PlaceReservation::query()
                ->where('event_id', $event->id)
                ->with('place:id,community_id,name')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
            $currentStatus = $event->status;
            $oldEvent      = [
                'starts_at' => $event->starts_at->format('Y-m-d H:i:s'),
                'ends_at'   => $event->ends_at->format('Y-m-d H:i:s'),
            ];
            $oldReservations = $currentReservations
                ->map(fn ($reservation): array => [
                    'place_id'      => $reservation->place_id,
                    'reserved_from' => $reservation->reserved_from->format('Y-m-d H:i:s'),
                    'reserved_to'   => $reservation->reserved_to->format('Y-m-d H:i:s'),
                    'is_primary'    => $reservation->is_primary,
                ])
                ->values()
                ->all();

            /** @var array<string, mixed> $changes */
            $changes = [];

            if ($oldEvent['starts_at'] !== $startsAt->format('Y-m-d H:i:s')) {
                $changes['starts_at'] = ['from' => $oldEvent['starts_at'], 'to' => $startsAt->format('Y-m-d H:i:s')];
            }

            if ($oldEvent['ends_at'] !== $endsAt->format('Y-m-d H:i:s')) {
                $changes['ends_at'] = ['from' => $oldEvent['ends_at'], 'to' => $endsAt->format('Y-m-d H:i:s')];
            }

            if ($event->is_external) {
                $changes = array_merge(
                    $changes,
                    $this->updateExternalLocation($event, $externalLocation ?? []),
                );
            } else {
                $normalizedReservations = $this->validateReservations(
                    $startsAt,
                    $endsAt,
                    $reservations,
                    $communityId,
                    $currentReservations->modelKeys(),
                );

                $event->community_id = $communityId;

                if ($oldReservations !== $normalizedReservations) {
                    $oldPrimary     = $currentReservations->firstWhere('is_primary', true);
                    $oldCommunityId = $oldPrimary === null
                        ? null
                        : Place::query()->whereKey($oldPrimary->place_id)->value('community_id');

                    if ($oldCommunityId !== $communityId) {
                        $changes['community_id'] = ['from' => $oldCommunityId, 'to' => $communityId];
                    }

                    $changes['reservations'] = ['from' => $oldReservations, 'to' => $normalizedReservations];

                    $event->reservations()->delete();
                    $event->reservations()->createMany($normalizedReservations);
                }
            }

            if ($changes === []) {
                throw ValidationException::withMessages([
                    'reschedule' => 'Nenhuma alteração foi informada para a remarcação.',
                ]);
            }

            $newStatus = $currentStatus === EventStatusEnum::CONFIRMED
                ? EventStatusEnum::RESCHEDULED
                : $currentStatus;

            $event->forceFill([
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
                'status'    => $newStatus,
            ])->save();

            if ($currentStatus !== EventStatusEnum::PENDING) {
                $this->eventLog->handle(
                    event: $event,
                    action: $currentStatus === EventStatusEnum::CONFIRMED
                        ? EventLogActionEnum::RESCHEDULED
                        : EventLogActionEnum::UPDATED,
                    user: $user,
                    fromStatus: $currentStatus,
                    toStatus: $newStatus,
                    changes: $changes,
                );
            }

            return $event->refresh()->load(['detail', 'reservations.place']);
        });
    }

    /**
     * @param  list<array{place_id: int|string, reserved_from: string, reserved_to: string, is_primary: bool}>  $reservations
     * @param  list<int>  $ignoredReservationIds
     * @return list<array{place_id: int, reserved_from: string, reserved_to: string, is_primary: bool}>
     */
    private function validateReservations(
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        array $reservations,
        ?int $communityId,
        array $ignoredReservationIds,
    ): array {
        $validated = Validator::make(
            [
                'community_id' => $communityId,
                'reservations' => $reservations,
            ],
            [
                'community_id'                 => ['required', 'integer', Rule::exists('communities', 'id')],
                'reservations'                 => ['required', 'array', 'min:1'],
                'reservations.*.place_id'      => ['required', 'integer', 'distinct', Rule::exists('places', 'id')],
                'reservations.*.reserved_from' => ['required', 'date_format:Y-m-d H:i:s'],
                'reservations.*.reserved_to'   => ['required', 'date_format:Y-m-d H:i:s'],
                'reservations.*.is_primary'    => ['required', 'boolean'],
            ],
            [
                'community_id.required'                 => 'Selecione a comunidade das novas reservas.',
                'reservations.required'                 => 'Configure ao menos uma reserva para o evento.',
                'reservations.min'                      => 'Configure ao menos uma reserva para o evento.',
                'reservations.*.place_id.distinct'      => 'Cada ambiente pode aparecer somente uma vez na proposta.',
                'reservations.*.reserved_from.required' => 'Informe o início de cada reserva.',
                'reservations.*.reserved_to.required'   => 'Informe o fim de cada reserva.',
            ],
        )->validate();

        $normalizedReservations = collect($validated['reservations'])
            ->map(fn (array $reservation): array => [
                'place_id'      => (int) $reservation['place_id'],
                'reserved_from' => CarbonImmutable::createFromFormat('Y-m-d H:i:s', $reservation['reserved_from'])
                    ->format('Y-m-d H:i:s'),
                'reserved_to' => CarbonImmutable::createFromFormat('Y-m-d H:i:s', $reservation['reserved_to'])
                    ->format('Y-m-d H:i:s'),
                'is_primary' => (bool) $reservation['is_primary'],
            ])
            ->values();

        $invalidIntervalIndex = $normalizedReservations->search(
            fn (array $reservation): bool => CarbonImmutable::parse($reservation['reserved_to'])
                ->lessThanOrEqualTo(CarbonImmutable::parse($reservation['reserved_from'])),
        );

        if ($invalidIntervalIndex !== false) {
            throw ValidationException::withMessages([
                "reservations.{$invalidIntervalIndex}.reserved_to" => 'O fim da reserva deve ser posterior ao início.',
            ]);
        }

        $primaryReservations = $normalizedReservations->where('is_primary', true);

        if ($primaryReservations->count() !== 1) {
            throw ValidationException::withMessages([
                'reservations' => 'A proposta deve conter exatamente uma reserva principal.',
            ]);
        }

        $primary = $primaryReservations->first();

        if (CarbonImmutable::parse($primary['reserved_from'])->greaterThan($startsAt)
            || CarbonImmutable::parse($primary['reserved_to'])->lessThan($endsAt)) {
            throw ValidationException::withMessages([
                'reservations' => 'A reserva principal deve cobrir integralmente o novo horário do evento.',
            ]);
        }

        $places = Place::query()
            ->whereKey($normalizedReservations->pluck('place_id'))
            ->where('community_id', $communityId)
            ->get();

        if ($places->count() !== $normalizedReservations->count()) {
            throw ValidationException::withMessages([
                'reservations' => 'Todas as reservas devem pertencer à comunidade selecionada.',
            ]);
        }

        $placeHours = $normalizedReservations->mapWithKeys(function (array $reservation) use ($startsAt, $endsAt): array {
            $reservedFrom = CarbonImmutable::parse($reservation['reserved_from']);
            $reservedTo   = CarbonImmutable::parse($reservation['reserved_to']);

            return [$reservation['place_id'] => [
                'before_hours' => ($startsAt->getTimestamp() - $reservedFrom->getTimestamp()) / 3600,
                'after_hours'  => ($reservedTo->getTimestamp() - $endsAt->getTimestamp()) / 3600,
            ]];
        })->all();
        $conflicts = $this->checkPlaceAvailability->handle(
            $startsAt,
            $endsAt,
            $places,
            $placeHours,
            $ignoredReservationIds,
        );

        if ($conflicts !== []) {
            $messages = ['reservations' => 'Existem conflitos de ambiente na proposta. Revise as reservas antes de confirmar.'];

            foreach ($conflicts as $conflict) {
                $index = $normalizedReservations->search(
                    fn (array $reservation): bool => $reservation['place_id'] === $conflict['requested_place']['id'],
                );

                if ($index !== false) {
                    $messages["reservations.{$index}.place_id"] = sprintf(
                        'O ambiente %s possui conflito no intervalo proposto.',
                        $conflict['requested_place']['name'],
                    );
                }
            }

            throw ValidationException::withMessages($messages);
        }

        return $normalizedReservations->all();
    }

    /**
     * @param  array{external_location_name?: string|null, external_location_address?: string|null, external_location_url?: string|null}  $externalLocation
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function updateExternalLocation(Event $event, array $externalLocation): array
    {
        $validated = Validator::make($externalLocation, [
            'external_location_name'    => ['required', 'string', 'max:255'],
            'external_location_address' => ['required', 'string', 'max:255'],
            'external_location_url'     => ['nullable', 'url', 'max:255'],
        ], [
            'external_location_name.required'    => 'Informe o nome do local externo.',
            'external_location_address.required' => 'Informe o endereço do local externo.',
        ])->validate();

        $detail  = EventDetail::query()->firstOrNew(['event_id' => $event->id]);
        $changes = [];

        foreach (array_keys($validated) as $attribute) {
            $newValue = $validated[$attribute] ?: null;
            $oldValue = $detail->getAttribute($attribute);

            if ($oldValue !== $newValue) {
                $changes[$attribute] = ['from' => $oldValue, 'to' => $newValue];
            }
        }

        $detail->fill(Arr::only($validated, [
            'external_location_name',
            'external_location_address',
            'external_location_url',
        ]));
        $detail->save();

        return $changes;
    }

    private function toDateTime(CarbonImmutable|string $date): CarbonImmutable
    {
        return $date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date);
    }
}
