<?php

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

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $groups = Group::query()->get();
        $places = Place::query()->get();
        $users = User::query()->get();

        if ($groups->isEmpty() || $places->isEmpty()) {
            return;
        }

        $primaryPlaces = $places->shuffle()->values();
        $eventIndex = 0;

        // Duas séries mensais, com quatro ocorrências cada = 8 eventos recorrentes.
        for ($series = 1; $series <= 2; $series++) {
            $group = $groups->random();
            $recurrenceCode = (string) Str::ulid();
            $baseDate = CarbonImmutable::now()
                ->addMonth($series)
                ->startOfMonth()
                ->addDays($series * 3)
                ->setTime($series === 1 ? 19 : 20, 0);

            for ($occurrence = 0; $occurrence < 4; $occurrence++) {
                $startsAt = $baseDate->addMonthsNoOverflow($occurrence);
                $endsAt = $startsAt->addHours(2);

                $event = Event::factory()->create([
                    'group_id' => $group->id,
                    'created_by_user_id' => $users->random()->id,
                    'name' => $series === 1
                        ? 'Encontro mensal - '.$group->name
                        : 'Reunião mensal - '.$group->name,
                    'type' => EventTypeEnum::MEETING,
                    'recurrence_code' => $recurrenceCode,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => EventStatusEnum::CONFIRMED,
                    'is_external' => false,
                    'is_public' => true,
                    'advertisable' => false,
                ]);

                $primaryPlace = $primaryPlaces[$eventIndex % $primaryPlaces->count()];
                $this->createReservations($event, $places, $primaryPlace);
                $eventIndex++;
            }
        }

        // Oito eventos únicos, em datas distintas.
        $uniqueNames = [
            'Jantar dançante',
            'Formação de lideranças',
            'Ensaio geral do coral',
            'Café comunitário',
            'Festa da comunidade',
            'Treinamento de informática',
            'Encontro de coordenadores',
            'Oficina de música',
        ];

        $uniqueTypes = [
            EventTypeEnum::FOOD,
            EventTypeEnum::COURSE,
            EventTypeEnum::REHEARSAL,
            EventTypeEnum::FOOD,
            EventTypeEnum::PARTY,
            EventTypeEnum::COURSE,
            EventTypeEnum::MEETING,
            EventTypeEnum::COURSE,
        ];

        $baseUniqueDate = CarbonImmutable::now()->addWeeks(3)->startOfDay();

        foreach ($uniqueNames as $index => $name) {
            $startsAt = $baseUniqueDate
                ->addDays(($index + 1) * 3)
                ->setTime(14 + ($index % 6), 0);
            $endsAt = $startsAt->addHours(random_int(2, 4));

            $event = Event::factory()->create([
                'group_id' => $groups->random()->id,
                'created_by_user_id' => $users->random()->id,
                'name' => $name,
                'type' => $uniqueTypes[$index],
                'recurrence_code' => null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => fake()->randomElement([
                    EventStatusEnum::PENDING,
                    EventStatusEnum::CONFIRMED,
                ]),
                'is_external' => false,
                'is_public' => true,
                'advertisable' => fake()->boolean(50),
            ]);

            $primaryPlace = $primaryPlaces[$eventIndex % $primaryPlaces->count()];
            $this->createReservations($event, $places, $primaryPlace);
            $eventIndex++;
        }
    }

    private function createReservations(Event $event, Collection $places, Place $primaryPlace): void
    {
        PlaceReservation::factory()->create([
            'event_id' => $event->id,
            'place_id' => $primaryPlace->id,
            'reserved_from' => $event->starts_at->copy()->subMinutes(random_int(30, 120)),
            'reserved_to' => $event->ends_at->copy()->addMinutes(random_int(30, 90)),
            'is_primary' => true,
        ]);

        $additionalPlaces = $places
            ->where('id', '!=', $primaryPlace->id)
            ->shuffle()
            ->take(random_int(0, min(2, max(0, $places->count() - 1))));

        foreach ($additionalPlaces as $place) {
            PlaceReservation::factory()->create([
                'event_id' => $event->id,
                'place_id' => $place->id,
                'reserved_from' => $event->starts_at->copy()->subMinutes(random_int(30, 180)),
                'reserved_to' => $event->ends_at->copy()->addMinutes(random_int(30, 120)),
                'is_primary' => false,
            ]);
        }
    }
}
