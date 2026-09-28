<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Mass;
use App\Models\MassSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class MassSeeder extends Seeder
{
    public function run(): void
    {
        $communities = Community::query()
            ->get()
            ->keyBy(fn (Community $community): string => strtoupper($community->abbreviation));

        $schedules = [
            ['MSM', CarbonInterface::TUESDAY, '19:30', 60, 'Missa Semanal'],
            ['MSM', CarbonInterface::WEDNESDAY, '15:00', 60, 'Missa com Novena'],
            ['MSM', CarbonInterface::THURSDAY, '19:30', 60, 'Missa Semanal'],
            ['MSM', CarbonInterface::FRIDAY, '07:30', 60, 'Missa Semanal'],
            ['MSM', CarbonInterface::SATURDAY, '15:00', 60, 'Missa de Sábado'],
            ['MSM', CarbonInterface::SATURDAY, '19:30', 60, 'Missa de Sábado'],
            ['MSM', CarbonInterface::SUNDAY, '08:00', 60, 'Missa Dominical'],
            ['MSM', CarbonInterface::SUNDAY, '10:00', 60, 'Missa Dominical'],

            ['BGC', CarbonInterface::SUNDAY, '10:00', 60, 'Missa Dominical'],
            ['NSM', CarbonInterface::SUNDAY, '09:30', 60, 'Missa Dominical'],
            ['NSP', CarbonInterface::SATURDAY, '18:00', 60, 'Missa da Comunidade'],
            ['PIL', CarbonInterface::FRIDAY, '15:00', 60, 'Missa Semanal'],
            ['SJN', CarbonInterface::SUNDAY, '18:00', 60, 'Missa Dominical'],
        ];

        $baseDate = CarbonImmutable::today();

        foreach ($schedules as [$abbreviation, $weekday, $startsAt, $durationMinutes, $motivation]) {
            $community = $communities->get($abbreviation);

            if (! $community) {
                continue;
            }

            $firstOccurrence = $this->nextWeekday($baseDate, $weekday);
            $lastOccurrence = $firstOccurrence->addWeeks(9);

            $schedule = MassSchedule::updateOrCreate(
                [
                    'community_id' => $community->id,
                    'weekday' => $weekday,
                    'starts_at' => $startsAt,
                ],
                [
                    'duration_minutes' => $durationMinutes,
                    'motivation' => $motivation,
                    'valid_from' => $firstOccurrence->toDateString(),
                    'valid_until' => $lastOccurrence->toDateString(),
                    'is_active' => true,
                ]
            );

            for ($week = 0; $week < 10; $week++) {
                $date = $firstOccurrence->addWeeks($week);

                if (! $schedule->masses()->whereDate('starts_at', $date->toDateString())->exists()) {
                    $schedule->generateMassForDate($date);
                }
            }
        }

        $extraordinaryMasses = [
            ['MSM', 'Missa da Padroeira', '2026-10-12 12:00:00', 90],
            ['NSM', 'Crisma', '2026-10-24 16:00:00', 120],
            ['MSM', 'Missa de Finados', '2026-11-02 19:30:00', 75],
            ['MSM', 'Primeira Eucaristia', '2026-11-14 10:00:00', 90],
            ['NSM', 'Missa de Encerramento de Retiro', '2026-11-27 19:30:00', 90],
        ];

        foreach ($extraordinaryMasses as [$abbreviation, $motivation, $startsAtValue, $durationMinutes]) {
            $community = $communities->get($abbreviation);

            if (! $community) {
                continue;
            }

            $startsAt = CarbonImmutable::parse($startsAtValue);

            Mass::firstOrCreate(
                [
                    'mass_schedule_id' => null,
                    'community_id' => $community->id,
                    'starts_at' => $startsAt,
                ],
                [
                    'motivation' => $motivation,
                    'ends_at' => $startsAt->addMinutes($durationMinutes),
                    'canceled_at' => null,
                ]
            );
        }
    }

    private function nextWeekday(CarbonImmutable $date, int $weekday): CarbonImmutable
    {
        while ($date->dayOfWeek !== $weekday) {
            $date = $date->addDay();
        }

        return $date;
    }
}
