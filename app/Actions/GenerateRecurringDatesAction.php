<?php

declare(strict_types=1);

namespace App\Actions;

use Carbon\CarbonImmutable;

class GenerateRecurringDatesAction
{
    /** @return list<string> */
    public function handle(
        int $weekday,
        string $periodicity,
        ?string $monthlyPosition,
        ?string $startDate,
        ?string $endDate,
        string $startsTime,
        CarbonImmutable $now,
    ): array {
        $start = $startDate === null ? $now->startOfDay() : CarbonImmutable::parse($startDate)->startOfDay();
        $end   = $endDate === null ? $now->endOfYear() : CarbonImmutable::parse($endDate)->endOfDay();

        if ($start > $end) {
            return [];
        }

        $dates = [];

        if ($periodicity === 'monthly') {
            for ($month = $start->startOfMonth(); $month <= $end; $month = $month->addMonthNoOverflow()) {
                $candidate = $this->monthlyDate($month, $weekday, $monthlyPosition);

                if ($candidate >= $start && $candidate <= $end && $this->isFutureStart($candidate, $startsTime, $now)) {
                    $dates[] = $candidate->toDateString();
                }
            }

            return $dates;
        }

        $candidate = $start->addDays(($weekday - $start->dayOfWeek + 7) % 7);
        $interval  = $periodicity === 'biweekly' ? 14 : 7;

        if ($startDate === null && ! $this->isFutureStart($candidate, $startsTime, $now)) {
            $candidate = $candidate->addWeek();
        }

        for (; $candidate <= $end; $candidate = $candidate->addDays($interval)) {
            if ($this->isFutureStart($candidate, $startsTime, $now)) {
                $dates[] = $candidate->toDateString();
            }
        }

        return $dates;
    }

    private function monthlyDate(CarbonImmutable $month, int $weekday, ?string $position): CarbonImmutable
    {
        if ($position === 'last') {
            $lastDay = $month->endOfMonth()->startOfDay();

            return $lastDay->subDays(($lastDay->dayOfWeek - $weekday + 7) % 7);
        }

        $firstDay        = $month->startOfMonth();
        $firstOccurrence = $firstDay->addDays(($weekday - $firstDay->dayOfWeek + 7) % 7);

        return $firstOccurrence->addWeeks((int) $position - 1);
    }

    private function isFutureStart(CarbonImmutable $date, string $startsTime, CarbonImmutable $now): bool
    {
        return CarbonImmutable::parse($date->toDateString().' '.$startsTime) > $now;
    }
}
