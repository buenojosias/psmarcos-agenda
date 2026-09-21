<?php

declare(strict_types=1);

use App\Models\Mass;
use App\Models\Community;
use App\Models\MassSchedule;
use Database\Seeders\MassSeeder;

it('refreshes existing schedules before creating future occurrences', function () {
    $this->travelTo('2026-09-20 00:00:00');

    Community::create(['name' => 'Matriz São Marcos', 'alias' => 'Matriz', 'abbreviation' => 'msm']);
    Community::create(['name' => 'Capela Nossa Senhora da Misericórdia', 'alias' => 'Misericórdia', 'abbreviation' => 'nsm']);

    app(MassSeeder::class)->run();

    $this->travelTo('2026-09-21 00:00:00');

    app(MassSeeder::class)->run();

    expect(MassSchedule::query()->count())->toBe(5)
        ->and(Mass::query()->count())->toBe(58);
});
