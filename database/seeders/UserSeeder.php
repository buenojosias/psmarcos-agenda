<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@email.com',
            'whatsapp' => null,
            'password' => '123456',
            'is_active' => true,
            'roles' => ['admin'],
        ]);
        User::create([
            'name' => 'Josias Bueno',
            'email' => 'josias@email.com',
            'whatsapp' => '41996881818',
            'password' => '123456',
            'is_active' => true,
            'roles' => ['member'],
        ]);

        User::factory()->count(3)->create();
    }
}
