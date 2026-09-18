<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@email.com',
            'whatsapp' => null,
            'is_active' => true,
            'roles' => ['admin'],
        ]);

        User::factory()->create([
            'name' => 'Member',
            'email' => 'member@email.com',
            'whatsapp' => null,
            'is_active' => true,
            'roles' => ['member'],
        ]);

        User::factory()->create([
            'name' => 'Secretary',
            'email' => 'secretary@email.com',
            'whatsapp' => null,
            'is_active' => true,
            'roles' => ['secretary'],
        ]);

        User::factory()->create([
            'name' => 'Pascom',
            'email' => 'pascom@email.com',
            'whatsapp' => null,
            'is_active' => true,
            'roles' => ['pascom'],
        ]);

        User::factory()->create([
            'name' => 'CPP',
            'email' => 'cpp@email.com',
            'whatsapp' => null,
            'is_active' => true,
            'roles' => ['cpp'],
        ]);

        User::factory()->create([
            'name' => 'Priest',
            'email' => 'priest@email.com',
            'whatsapp' => null,
            'is_active' => true,
            'roles' => ['priest'],
        ]);

        User::factory()->create([
            'name' => 'Josias Bueno',
            'email' => 'josias@email.com',
            'is_active' => true,
            'roles' => ['member'],
        ]);

        User::factory()->count(3)->create();
    }
}
