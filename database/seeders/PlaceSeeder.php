<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        $places = [
            [
                'name' => 'Salão Maior',
                'community_id' => 1,
                'main_place_id' => null,
            ],
            [
                'name' => 'Salão Menor',
                'community_id' => 1,
                'main_place_id' => null,
            ],
            [
                'name' => 'Centro Catequético',
                'community_id' => 1,
                'main_place_id' => null,
            ],
            [
                'name' => 'Sala 1',
                'community_id' => 1,
                'main_place_id' => 3,
            ],
            [
                'name' => 'Sala 2',
                'community_id' => 1,
                'main_place_id' => 3,
            ],
            [
                'name' => 'Salão de Festas',
                'community_id' => 4,
                'main_place_id' => null,
            ],
            [
                'name' => 'Nave',
                'community_id' => 2,
                'main_place_id' => null,
            ],
            [
                'name' => 'Nave',
                'community_id' => 3,
                'main_place_id' => null,
            ]
        ];
        
        Place::insert($places);
    }
}
