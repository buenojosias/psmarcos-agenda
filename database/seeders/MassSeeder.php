<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Mass;
use App\Models\MassSchedule;
use App\Models\Place;
use App\Models\PlaceReservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use RuntimeException;

class MassSeeder extends Seeder
{
    public function run(): void
    {
        $communities = Community::query()
            ->whereIn('abbreviation', ['msm', 'nsm'])
            ->get()
            ->keyBy('abbreviation');

        $matriz = $communities->get('msm');
        $misericordia = $communities->get('nsm');

        if (! $matriz || ! $misericordia) {
            throw new RuntimeException(
                'As comunidades Matriz e Misericórdia devem existir antes de executar o MassSeeder.'
            );
        }

        /*
         * Cinco horários regulares.
         *
         * weekday segue Carbon:
         * 0 = domingo
         * 1 = segunda
         * ...
         * 6 = sábado
         */
        $schedules = [
            [
                'community' => $matriz,
                'weekday' => CarbonInterface::SUNDAY,
                'starts_at' => '08:00',
                'duration_minutes' => 60,
                'motivation' => 'Missa Dominical',
            ],
            [
                'community' => $misericordia,
                'weekday' => CarbonInterface::SUNDAY,
                'starts_at' => '09:30',
                'duration_minutes' => 60,
                'motivation' => 'Missa Dominical',
            ],
            [
                'community' => $matriz,
                'weekday' => CarbonInterface::SUNDAY,
                'starts_at' => '10:00',
                'duration_minutes' => 60,
                'motivation' => 'Missa Dominical',
            ],
            [
                'community' => $matriz,
                'weekday' => CarbonInterface::TUESDAY,
                'starts_at' => '19:30',
                'duration_minutes' => 60,
                'motivation' => 'Missa Semanal',
            ],
            [
                'community' => $matriz,
                'weekday' => CarbonInterface::WEDNESDAY,
                'starts_at' => '15:00',
                'duration_minutes' => 60,
                'motivation' => 'Missa com Novena',
            ],
        ];

        /*
         * Usamos uma data-base fixa para deixar o seeder previsível.
         * O helper localizará a primeira ocorrência correta para cada
         * dia da semana.
         */
        $baseDate = CarbonImmutable::create(2026, 9, 20)->startOfDay();

        foreach ($schedules as $data) {
            $firstOccurrence = $this->nextWeekday(
                $baseDate,
                $data['weekday']
            );

            $lastOccurrence = $firstOccurrence->addWeeks(9);

            $schedule = MassSchedule::firstOrCreate(
                [
                    'community_id' => $data['community']->id,
                    'weekday' => $data['weekday'],
                    'starts_at' => $data['starts_at'],
                ],
                [
                    'duration_minutes' => $data['duration_minutes'],
                    'motivation' => $data['motivation'],
                    'valid_from' => $firstOccurrence->toDateString(),
                    'valid_until' => $lastOccurrence->toDateString(),
                    'is_active' => true,
                ]
            );

            /*
             * Dez ocorrências concretas para cada MassSchedule.
             */
            for ($week = 0; $week < 10; $week++) {
                $date = $firstOccurrence->addWeeks($week);

                [$hour, $minute] = array_map(
                    'intval',
                    explode(':', $data['starts_at'])
                );

                $startsAt = $date->setTime($hour, $minute);

                $endsAt = $startsAt->addMinutes(
                    $data['duration_minutes']
                );

                $mass = Mass::firstOrCreate(
                    [
                        'mass_schedule_id' => $schedule->id,
                        'starts_at' => $startsAt,
                    ],
                    [
                        'community_id' => $data['community']->id,
                        'motivation' => $data['motivation'],
                        'ends_at' => $endsAt,
                        'canceled_at' => null,
                    ]
                );

                $this->createReservations($mass);
            }
        }

        /*
         * Cinco missas extraordinárias.
         * mass_schedule_id permanece null.
         */
        $extraordinaryMasses = [
            [
                'community' => $matriz,
                'motivation' => 'Missa da Padroeira',
                'starts_at' => '2026-10-12 12:00:00',
                'duration_minutes' => 90,
            ],
            [
                'community' => $misericordia,
                'motivation' => 'Crisma',
                'starts_at' => '2026-10-24 16:00:00',
                'duration_minutes' => 120,
            ],
            [
                'community' => $matriz,
                'motivation' => 'Missa de Finados',
                'starts_at' => '2026-11-02 19:30:00',
                'duration_minutes' => 75,
            ],
            [
                'community' => $matriz,
                'motivation' => 'Primeira Eucaristia',
                'starts_at' => '2026-11-14 10:00:00',
                'duration_minutes' => 90,
            ],
            [
                'community' => $misericordia,
                'motivation' => 'Missa de Encerramento de Retiro',
                'starts_at' => '2026-11-27 19:30:00',
                'duration_minutes' => 90,
            ],
        ];

        foreach ($extraordinaryMasses as $data) {
            $startsAt = CarbonImmutable::parse($data['starts_at']);
            $endsAt = $startsAt->addMinutes($data['duration_minutes']);

            $mass = Mass::firstOrCreate(
                [
                    'mass_schedule_id' => null,
                    'community_id' => $data['community']->id,
                    'starts_at' => $startsAt,
                ],
                [
                    'motivation' => $data['motivation'],
                    'ends_at' => $endsAt,
                    'canceled_at' => null,
                ]
            );

            $this->createReservations($mass);
        }
    }

    private function nextWeekday(
        CarbonImmutable $date,
        int $weekday
    ): CarbonImmutable {
        while ($date->dayOfWeek !== $weekday) {
            $date = $date->addDay();
        }

        return $date;
    }

    private function createReservations(Mass $mass): void
    {
        $places = Place::query()
            ->where('community_id', $mass->community_id)
            ->whereIn('name', [
                'Nave',
                'Sacristia',
                'Estacionamento',
            ])
            ->get();

        if ($places->count() !== 3) {
            throw new RuntimeException(
                "A comunidade {$mass->community_id} precisa possuir Nave, Sacristia e Estacionamento."
            );
        }

        foreach ($places as $place) {
            PlaceReservation::updateOrCreate(
                [
                    'mass_id' => $mass->id,
                    'place_id' => $place->id,
                ],
                [
                    'event_id' => null,
                    'reserved_from' => $mass->starts_at->copy()->subMinutes(30),
                    'reserved_to' => $mass->ends_at->copy()->addMinutes(30),
                    'is_primary' => $place->name === 'Nave',
                ]
            );
        }
    }
}