<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Place;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use App\Enums\EventTypeEnum;
use App\Enums\EventStatusEnum;
use Illuminate\Database\Seeder;
use App\Models\PlaceReservation;
use Illuminate\Support\Collection;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $groups = Group::query()->get();
        $places = Place::query()->get();
        $users  = User::query()->get();

        if ($groups->isEmpty() || $places->isEmpty()) {
            return;
        }

        $primaryPlaces = $places->shuffle()->values();
        $eventIndex    = 0;

        // Duas séries mensais, com quatro ocorrências cada = 8 eventos recorrentes.
        for ($series = 1; $series <= 2; $series++) {
            $group           = $groups->random();
            $seriesPlace     = $primaryPlaces[$eventIndex % $primaryPlaces->count()];
            $communityPlaces = $places->where('community_id', $seriesPlace->community_id)->values();
            $recurrenceCode  = (string) Str::ulid();
            $baseDate        = CarbonImmutable::now()
                ->addMonth($series)
                ->startOfMonth()
                ->addDays($series * 3)
                ->setTime($series === 1 ? 19 : 20, 0);

            for ($occurrence = 0; $occurrence < 4; $occurrence++) {
                $startsAt     = $baseDate->addMonthsNoOverflow($occurrence);
                $endsAt       = $startsAt->addHours(2);
                $primaryPlace = $communityPlaces[$occurrence % $communityPlaces->count()];

                $event = Event::factory()->create([
                    'community_id'       => $seriesPlace->community_id,
                    'group_id'           => $group->id,
                    'created_by_user_id' => $users->random()->id,
                    'name'               => $series === 1
                        ? 'Encontro mensal - '.$group->name
                        : 'Reunião mensal - '.$group->name,
                    'complement' => $occurrence % 2 === 0
                        ? ($series === 1 ? 'Partilha e formação' : 'Planejamento das atividades')
                        : null,
                    'type'            => EventTypeEnum::MEETING,
                    'recurrence_code' => $recurrenceCode,
                    'starts_at'       => $startsAt,
                    'ends_at'         => $endsAt,
                    'status'          => EventStatusEnum::CONFIRMED,
                    'is_external'     => false,
                    'is_public'       => true,
                    'advertisable'    => false,
                ]);

                $this->createReservations($event, $communityPlaces, $primaryPlace);
                $eventIndex++;
            }
        }

        // Oito eventos únicos, em datas distintas.
        $uniqueEvents = [
            ['name' => 'Jantar dançante', 'type' => EventTypeEnum::FOOD, 'complement' => 'Noite de confraternização'],
            ['name' => 'Formação de lideranças', 'type' => EventTypeEnum::COURSE, 'complement' => null],
            ['name' => 'Ensaio geral do coral', 'type' => EventTypeEnum::REHEARSAL, 'complement' => 'Preparação para a celebração'],
            ['name' => 'Café comunitário', 'type' => EventTypeEnum::FOOD, 'complement' => null],
            ['name' => 'Festa da comunidade', 'type' => EventTypeEnum::PARTY, 'complement' => 'Confraternização paroquial'],
            ['name' => 'Treinamento de informática', 'type' => EventTypeEnum::COURSE, 'complement' => null],
            ['name' => 'Encontro de coordenadores', 'type' => EventTypeEnum::MEETING, 'complement' => 'Planejamento das atividades'],
            ['name' => 'Oficina de música', 'type' => EventTypeEnum::COURSE, 'complement' => null],
        ];

        $baseUniqueDate = CarbonImmutable::now()->addWeeks(3)->startOfDay();

        foreach ($uniqueEvents as $index => $uniqueEvent) {
            $startsAt = $baseUniqueDate
                ->addDays(($index + 1) * 3)
                ->setTime(14 + ($index % 6), 0);
            $endsAt          = $startsAt->addHours(random_int(2, 4));
            $primaryPlace    = $primaryPlaces[$eventIndex % $primaryPlaces->count()];
            $communityPlaces = $places->where('community_id', $primaryPlace->community_id)->values();

            $event = Event::factory()->create([
                'community_id'       => $primaryPlace->community_id,
                'group_id'           => $groups->random()->id,
                'created_by_user_id' => $users->random()->id,
                'name'               => $uniqueEvent['name'],
                'complement'         => $uniqueEvent['complement'],
                'type'               => $uniqueEvent['type'],
                'recurrence_code'    => null,
                'starts_at'          => $startsAt,
                'ends_at'            => $endsAt,
                'status'             => fake()->randomElement([
                    EventStatusEnum::PENDING,
                    EventStatusEnum::CONFIRMED,
                ]),
                'is_external'  => false,
                'is_public'    => true,
                'advertisable' => fake()->boolean(50),
            ]);

            $this->createReservations($event, $communityPlaces, $primaryPlace);
            $eventIndex++;
        }
    }

    private function createReservations(Event $event, Collection $communityPlaces, Place $primaryPlace): void
    {
        PlaceReservation::factory()->create([
            'event_id'      => $event->id,
            'place_id'      => $primaryPlace->id,
            'reserved_from' => $event->starts_at->copy()->floorMinutes(15)->subMinutes(random_int(2, 8) * 15),
            'reserved_to'   => $event->ends_at->copy()->ceilMinutes(15)->addMinutes(random_int(2, 6) * 15),
            'is_primary'    => true,
        ]);

        $additionalPlaces = $communityPlaces
            ->where('id', '!=', $primaryPlace->id)
            ->shuffle()
            ->take(random_int(0, min(2, max(0, $communityPlaces->count() - 1))));

        foreach ($additionalPlaces as $place) {
            PlaceReservation::factory()->create([
                'event_id'      => $event->id,
                'place_id'      => $place->id,
                'reserved_from' => $event->starts_at->copy()->floorMinutes(15)->subMinutes(random_int(2, 12) * 15),
                'reserved_to'   => $event->ends_at->copy()->ceilMinutes(15)->addMinutes(random_int(2, 8) * 15),
                'is_primary'    => false,
            ]);
        }
    }
}
