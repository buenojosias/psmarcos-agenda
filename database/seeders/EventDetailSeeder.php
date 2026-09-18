<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventDetail;
use Illuminate\Database\Seeder;

class EventDetailSeeder extends Seeder
{
    public function run(): void
    {
        $events = Event::query()->doesntHave('detail')->inRandomOrder()->get();

        foreach ($events->take((int) ceil($events->count() / 2)) as $event) {
            EventDetail::factory()->for($event)->create();
        }
    }
}
