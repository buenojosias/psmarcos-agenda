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
            'name' => 'Josias Bueno',
            'email' => 'josias@email.com',
            'whatsapp' => '41996881818',
            'password' => bcrypt('123456'),
            'is_active' => true,
            'roles' => ['user'],
        ]);

        User::factory(3)->insert();
    }
}
