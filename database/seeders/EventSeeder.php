<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use App\Models\PlaceReservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class EventSeeder extends Seeder
{
    private const array MASS_PLACE_NAMES = ['Nave', 'Sacristia', 'Estacionamento'];

    public function run(): void
    {
        $groups = Group::query()
            ->whereNotNull('community_id')
            ->orderBy('community_id')
            ->orderBy('id')
            ->get();

        $users = User::query()->orderBy('id')->get();

        if ($groups->isEmpty() || $users->isEmpty()) {
            return;
        }

        $placesByCommunity = Place::query()
            ->orderBy('id')
            ->get()
            ->groupBy('community_id');

        $windowStart = CarbonImmutable::today()->addDays(3)->startOfDay();
        $statuses = [
            EventStatusEnum::CONFIRMED,
            EventStatusEnum::PENDING,
            EventStatusEnum::CONFIRMED,
            EventStatusEnum::RESCHEDULED,
            EventStatusEnum::CONFIRMED,
            EventStatusEnum::REFUSED,
            EventStatusEnum::CANCELED,
        ];

        foreach ($groups as $groupIndex => $group) {
            $communityPlaces = $placesByCommunity->get($group->community_id, collect())->values();

            if ($communityPlaces->isEmpty()) {
                continue;
            }

            $recurrenceCode = (string) Str::ulid();
            $recurringOffsets = [1, 8, 15];

            foreach ($recurringOffsets as $occurrence => $dayOffset) {
                $desiredStartsAt = $windowStart
                    ->addDays($dayOffset)
                    ->setTime(19 + (($groupIndex + $occurrence) % 2), ($groupIndex % 3) * 10);
                $durationMinutes = 90 + (($groupIndex + $occurrence) % 2) * 30;
                $reservationQuantity = 1 + (($groupIndex + $occurrence) % 3);

                [$startsAt, $endsAt, $selectedPlaces] = $this->findAvailableSchedule(
                    $desiredStartsAt,
                    $durationMinutes,
                    $communityPlaces,
                    $reservationQuantity,
                    $groupIndex + $occurrence,
                );

                $event = Event::factory()->create([
                    'community_id' => $group->community_id,
                    'group_id' => $group->id,
                    'created_by_user_id' => $users[($groupIndex + $occurrence) % $users->count()]->id,
                    'name' => $this->recurringName($group),
                    'complement' => $occurrence === 0 ? 'Encontro regular do grupo' : null,
                    'type' => $this->recurringType($group),
                    'recurrence_code' => $recurrenceCode,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => $statuses[($groupIndex + $occurrence) % count($statuses)],
                    'is_external' => false,
                    'is_public' => ($groupIndex + $occurrence) % 4 !== 0,
                    'advertisable' => false,
                ]);

                $this->createReservations($event, $selectedPlaces);
            }

            $sporadicEvents = [
                ['offset' => 3, 'name' => 'Formação de lideranças', 'type' => EventTypeEnum::COURSE, 'complement' => 'Momento de formação e planejamento'],
                ['offset' => 6, 'name' => 'Encontro de confraternização', 'type' => EventTypeEnum::FELLOWSHIP, 'complement' => 'Integração entre participantes e famílias'],
                ['offset' => 11, 'name' => 'Reunião de planejamento', 'type' => EventTypeEnum::MEETING, 'complement' => 'Organização das próximas atividades'],
                ['offset' => 18, 'name' => 'Café comunitário', 'type' => EventTypeEnum::FOOD, 'complement' => 'Convivência e partilha após as atividades'],
            ];

            foreach ($sporadicEvents as $sporadicIndex => $data) {
                $isExternal = $sporadicIndex === 3 && $groupIndex % 12 === 0;
                $desiredStartsAt = $windowStart
                    ->addDays($data['offset'])
                    ->setTime(14 + (($groupIndex + $sporadicIndex) % 6), (($groupIndex + $sporadicIndex) % 4) * 10);
                $durationMinutes = 120 + (($groupIndex + $sporadicIndex) % 3) * 30;
                $reservationQuantity = 1 + (($groupIndex + $sporadicIndex + 1) % 3);

                if ($isExternal) {
                    $startsAt = $desiredStartsAt;
                    $endsAt = $startsAt->addMinutes($durationMinutes);
                    $selectedPlaces = collect();
                } else {
                    [$startsAt, $endsAt, $selectedPlaces] = $this->findAvailableSchedule(
                        $desiredStartsAt,
                        $durationMinutes,
                        $communityPlaces,
                        $reservationQuantity,
                        $groupIndex + $sporadicIndex + 4,
                    );
                }

                $event = Event::factory()->create([
                    'community_id' => $isExternal ? null : $group->community_id,
                    'group_id' => $group->id,
                    'created_by_user_id' => $users[($groupIndex + $sporadicIndex + 2) % $users->count()]->id,
                    'name' => $isExternal ? 'Visita pastoral externa' : $data['name'].' - '.$group->name,
                    'complement' => $isExternal ? 'Atividade realizada fora das dependências da paróquia' : $data['complement'],
                    'type' => $data['type'],
                    'recurrence_code' => null,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => $statuses[($groupIndex + $sporadicIndex + 3) % count($statuses)],
                    'is_external' => $isExternal,
                    'is_public' => ($groupIndex + $sporadicIndex) % 3 !== 0,
                    'advertisable' => ($groupIndex + $sporadicIndex) % 2 === 0,
                ]);

                if (! $isExternal) {
                    $this->createReservations($event, $selectedPlaces);
                }
            }
        }

        $this->assertReservationIntegrity();
    }

    private function recurringName(Group $group): string
    {
        $name = $group->name;

        return match (true) {
            str_contains($name, 'Coral') => 'Ensaio semanal - '.$name,
            str_contains($name, 'Catequese') => 'Encontro de catequese - '.$name,
            str_contains($name, 'Legião de Maria') => 'Reunião semanal - '.$name,
            str_contains($name, 'Liturgia') => 'Preparação litúrgica - '.$name,
            str_contains($name, 'Oração') => 'Encontro de oração - '.$name,
            default => 'Encontro regular - '.$name,
        };
    }

    private function recurringType(Group $group): EventTypeEnum
    {
        return match (true) {
            str_contains($group->name, 'Coral') => EventTypeEnum::REHEARSAL,
            str_contains($group->name, 'Catequese') || str_contains($group->name, 'Escola da Fé') => EventTypeEnum::COURSE,
            default => EventTypeEnum::MEETING,
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: Collection<int, Place>}
     */
    private function findAvailableSchedule(
        CarbonImmutable $desiredStartsAt,
        int $durationMinutes,
        Collection $communityPlaces,
        int $quantity,
        int $rotationOffset,
    ): array {
        $quantity = min($quantity, $communityPlaces->count());

        for ($attempt = 0; $attempt < 24; $attempt++) {
            $startsAt = $desiredStartsAt->addMinutes($attempt * 30);
            $endsAt = $startsAt->addMinutes($durationMinutes);

            // Usa a maior margem possível das reservas que serão criadas abaixo.
            $reservedFrom = $startsAt->subMinutes(50);
            $reservedTo = $endsAt->addMinutes(65);

            $availablePlaces = $this->orderedPlaces($communityPlaces, $rotationOffset + $attempt)
                ->reject(fn (Place $place): bool => $this->hasReservationConflict($place, $reservedFrom, $reservedTo))
                ->take($quantity)
                ->values();

            if ($availablePlaces->count() === $quantity) {
                return [$startsAt, $endsAt, $availablePlaces];
            }
        }

        throw new RuntimeException(sprintf(
            'Não foi possível encontrar %d ambiente(s) livre(s) para um evento a partir de %s.',
            $quantity,
            $desiredStartsAt->toDateTimeString(),
        ));
    }

    /** @return Collection<int, Place> */
    private function orderedPlaces(Collection $communityPlaces, int $rotationOffset): Collection
    {
        $generalPlaces = $communityPlaces
            ->reject(fn (Place $place): bool => in_array($place->name, self::MASS_PLACE_NAMES, true))
            ->values();

        $massPlaces = $communityPlaces
            ->filter(fn (Place $place): bool => in_array($place->name, self::MASS_PLACE_NAMES, true))
            ->values();

        $ordered = $generalPlaces->concat($massPlaces)->values();

        if ($ordered->isEmpty()) {
            return $ordered;
        }

        $offset = $rotationOffset % $ordered->count();

        return $ordered->slice($offset)
            ->concat($ordered->slice(0, $offset))
            ->values();
    }

    private function hasReservationConflict(
        Place $place,
        CarbonImmutable $reservedFrom,
        CarbonImmutable $reservedTo,
    ): bool {
        return PlaceReservation::query()
            ->where('place_id', $place->id)
            ->where('reserved_from', '<', $reservedTo)
            ->where('reserved_to', '>', $reservedFrom)
            ->exists();
    }

    /** @param Collection<int, Place> $selectedPlaces */
    private function createReservations(Event $event, Collection $selectedPlaces): void
    {
        foreach ($selectedPlaces as $index => $place) {
            $beforeMinutes = [20, 35, 50][$index % 3];
            $afterMinutes = [15, 40, 65][$index % 3];

            PlaceReservation::query()->create([
                'event_id' => $event->id,
                'mass_id' => null,
                'place_id' => $place->id,
                'reserved_from' => $event->starts_at->copy()->subMinutes($beforeMinutes),
                'reserved_to' => $event->ends_at->copy()->addMinutes($afterMinutes),
                'is_primary' => $index === 0,
            ]);
        }
    }

    private function assertReservationIntegrity(): void
    {
        PlaceReservation::query()
            ->with('place:id,name')
            ->orderBy('place_id')
            ->orderBy('reserved_from')
            ->get()
            ->groupBy('place_id')
            ->each(function (Collection $reservations): void {
                $previous = null;

                foreach ($reservations as $reservation) {
                    $duration = $reservation->reserved_from->diffInMinutes($reservation->reserved_to);

                    if ($duration < 30) {
                        throw new RuntimeException(sprintf(
                            'Reserva %d do ambiente %s possui apenas %d minutos.',
                            $reservation->id,
                            $reservation->place?->name ?? '#'.$reservation->place_id,
                            $duration,
                        ));
                    }

                    if ($previous !== null && $previous->reserved_to->gt($reservation->reserved_from)) {
                        throw new RuntimeException(sprintf(
                            'Sobreposição detectada no ambiente %s entre as reservas %d (%s–%s) e %d (%s–%s).',
                            $reservation->place?->name ?? '#'.$reservation->place_id,
                            $previous->id,
                            $previous->reserved_from->format('d/m/Y H:i'),
                            $previous->reserved_to->format('d/m/Y H:i'),
                            $reservation->id,
                            $reservation->reserved_from->format('d/m/Y H:i'),
                            $reservation->reserved_to->format('d/m/Y H:i'),
                        ));
                    }

                    $previous = $reservation;
                }
            });
    }
}
