<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = '$2y$12$oPub6RbUin.ZKwm2xxVi1.QVSiNbJ51TflzhNlmwhLMWq5QM1wG52';

        User::create([
            'name' => 'Admin',
            'email' => 'admin@email.com',
            'whatsapp' => null,
            'password' => $password,
            'is_active' => true,
            'roles' => ['admin'],
        ]);

        User::create([
            'name' => 'Member',
            'email' => 'member@email.com',
            'whatsapp' => null,
            'password' => $password,
            'is_active' => true,
            'roles' => ['member'],
        ]);

        User::create([
            'name' => 'Secretary',
            'email' => 'secretary@email.com',
            'whatsapp' => null,
            'password' => $password,
            'is_active' => true,
            'roles' => ['secretary'],
        ]);

        User::create([
            'name' => 'Pascom',
            'email' => 'pascom@email.com',
            'whatsapp' => null,
            'password' => $password,
            'is_active' => true,
            'roles' => ['pascom'],
        ]);

        User::create([
            'name' => 'CPP',
            'email' => 'cpp@email.com',
            'whatsapp' => null,
            'password' => $password,
            'is_active' => true,
            'roles' => ['cpp'],
        ]);

        User::create([
            'name' => 'Priest',
            'email' => 'priest@email.com',
            'whatsapp' => null,
            'password' => $password,
            'is_active' => true,
            'roles' => ['priest'],
        ]);

        User::create([
            'name' => 'Josias Bueno',
            'email' => 'josias@email.com',
            'whatsapp' => '41996881818',
            'password' => $password,
            'is_active' => true,
            'roles' => ['member'],
        ]);

        User::factory()->count(3)->create();
    }
}
