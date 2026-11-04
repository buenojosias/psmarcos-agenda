<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use App\Actions\GenerateRecurringDatesAction;

it('starts weekly occurrences at the next future calendar occurrence and stops at year end', function () {
    $dates = (new GenerateRecurringDatesAction)->handle(
        1, 'weekly', null, null, null, '09:00', CarbonImmutable::parse('2026-12-21 10:00:00'),
    );

    expect($dates)->toBe(['2026-12-28']);
});

it('anchors fortnightly intervals to the first selected weekday on or after the start date', function () {
    $dates = (new GenerateRecurringDatesAction)->handle(
        2, 'biweekly', null, '2026-09-21', '2026-10-21', '09:00', CarbonImmutable::parse('2026-09-21 10:00:00'),
    );

    expect($dates)->toBe(['2026-09-22', '2026-10-06', '2026-10-20']);
});

it('anchors a fortnightly schedule without a start date to the next future weekday', function () {
    $dates = (new GenerateRecurringDatesAction)->handle(
        1, 'biweekly', null, null, '2026-10-20', '09:00', CarbonImmutable::parse('2026-09-21 10:00:00'),
    );

    expect($dates)->toBe(['2026-09-28', '2026-10-12']);
});

it('finds the selected monthly weekday position across month boundaries', function (string $position, array $expected) {
    $dates = (new GenerateRecurringDatesAction)->handle(
        1, 'monthly', $position, '2026-09-01', '2026-11-30', '09:00', CarbonImmutable::parse('2026-08-31 10:00:00'),
    );

    expect($dates)->toBe($expected);
})->with([
    'first Monday'  => ['1', ['2026-09-07', '2026-10-05', '2026-11-02']],
    'fourth Monday' => ['4', ['2026-09-28', '2026-10-26', '2026-11-23']],
    'last Monday'   => ['last', ['2026-09-28', '2026-10-26', '2026-11-30']],
]);

it('keeps only dates inside the selected period and after the current time', function () {
    $dates = (new GenerateRecurringDatesAction)->handle(
        2, 'weekly', null, '2026-09-22', '2026-10-06', '09:00', CarbonImmutable::parse('2026-09-22 10:00:00'),
    );

    expect($dates)->toBe(['2026-09-29', '2026-10-06']);
});
