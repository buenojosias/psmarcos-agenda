<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        $matriz = Community::where('abbreviation', 'msm')->firstOrFail();
        $beato = Community::where('abbreviation', 'bgc')->firstOrFail();
        $misericordia = Community::where('abbreviation', 'nsm')->firstOrFail();
        $perseveranca = Community::where('abbreviation', 'nsp')->firstOrFail();

        Place::updateOrCreate(
            [
                'community_id' => $matriz->id,
                'name' => 'Salão Maior',
            ],
            ['main_place_id' => null]
        );

        Place::updateOrCreate(
            [
                'community_id' => $matriz->id,
                'name' => 'Salão Menor',
            ],
            ['main_place_id' => null]
        );

        $centroCatequetico = Place::updateOrCreate(
            [
                'community_id' => $matriz->id,
                'name' => 'Centro Catequético',
            ],
            ['main_place_id' => null]
        );

        Place::updateOrCreate(
            [
                'community_id' => $matriz->id,
                'name' => 'Sala 1',
            ],
            ['main_place_id' => $centroCatequetico->id]
        );

        Place::updateOrCreate(
            [
                'community_id' => $matriz->id,
                'name' => 'Sala 2',
            ],
            ['main_place_id' => $centroCatequetico->id]
        );

        Place::updateOrCreate(
            [
                'community_id' => $perseveranca->id,
                'name' => 'Salão de Festas',
            ],
            ['main_place_id' => null]
        );

        Place::updateOrCreate(
            [
                'community_id' => $beato->id,
                'name' => 'Nave',
            ],
            ['main_place_id' => null]
        );

        foreach ([$matriz, $misericordia] as $community) {
            foreach (['Nave', 'Sacristia', 'Estacionamento'] as $name) {
                $place = Place::updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'name' => $name,
                    ],
                    ['main_place_id' => null]
                );

                DB::table('community_mass_place')->updateOrInsert(
                    [
                        'community_id' => $community->id,
                        'place_id' => $place->id,
                    ],
                    [
                        'is_primary' => $name === 'Nave',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
