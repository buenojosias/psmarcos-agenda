<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CommunitySeeder::class,
            UserSeeder::class,
            PlaceSeeder::class,
            MassSeeder::class,
            GroupSeeder::class,
            EventSeeder::class,
            EventDetailSeeder::class,
        ]);
    }
}
