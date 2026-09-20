<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\Place;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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

        /*
         * Ambientes padrão de missa.
         */
        foreach ([$matriz, $misericordia] as $community) {
            foreach ([
                'Nave',
                'Sacristia',
                'Estacionamento',
            ] as $name) {
                $place = Place::updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'name' => $name,
                    ],
                    [
                        'main_place_id' => null,
                    ]
                );

                /*
                 * Remova este bloco se o Codex tiver dado outro nome
                 * para a pivot de espaços padrão de missa.
                 */
                DB::table('community_mass_place')->updateOrInsert(
                    [
                        'community_id' => $community->id,
                        'place_id' => $place->id,
                    ],
                    [
                        'is_primary' => $name === 'Nave',
                    ]
                );
            }
        }
    }
}
