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
        $communities = Community::query()->get()->keyBy(fn (Community $community): string => strtoupper($community->abbreviation));

        $placesByCommunity = [
            'MSM' => [
                'Nave',
                'Sacristia',
                'Estacionamento',
                'Salão Maior',
                'Salão Menor',
                'Centro Catequético',
                'Sala 1',
                'Sala 2',
                'Sala 3',
                'Sala 4',
                'Sala da Pastoral',
                'Sala de Reuniões',
                'Cozinha',
                'Pátio',
            ],
            'BGC' => [
                'Nave',
                'Sacristia',
                'Estacionamento',
                'Salão Comunitário',
                'Sala de Catequese 1',
                'Sala de Catequese 2',
                'Cozinha',
                'Pátio',
            ],
            'NSM' => [
                'Nave',
                'Sacristia',
                'Estacionamento',
                'Salão Comunitário',
                'Sala de Catequese',
                'Sala de Reuniões',
                'Cozinha',
                'Pátio',
            ],
            'NSP' => [
                'Nave',
                'Sacristia',
                'Estacionamento',
                'Salão de Festas',
                'Sala de Catequese 1',
                'Sala de Catequese 2',
                'Cozinha',
                'Pátio',
            ],
            'PIL' => [
                'Nave',
            ],
            'SJN' => [
                'Nave',
                'Sacristia',
                'Estacionamento',
                'Salão Comunitário',
                'Sala de Catequese 1',
                'Sala de Catequese 2',
                'Cozinha',
                'Pátio',
            ],
        ];

        foreach ($placesByCommunity as $abbreviation => $names) {
            $community = $communities->get($abbreviation);

            if (! $community) {
                continue;
            }

            $createdPlaces = collect();

            foreach ($names as $name) {
                $mainPlaceId = null;

                if ($abbreviation === 'MSM' && str_starts_with($name, 'Sala ') && preg_match('/^Sala \\d$/', $name)) {
                    $mainPlaceId = $createdPlaces->get('Centro Catequético')?->id;
                }

                $place = Place::updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'name'         => $name,
                    ],
                    ['main_place_id' => $mainPlaceId]
                );

                $createdPlaces->put($name, $place);
            }

            foreach (['Nave', 'Sacristia', 'Estacionamento'] as $massPlaceName) {
                $place = $createdPlaces->get($massPlaceName);

                if (! $place) {
                    continue;
                }

                DB::table('community_mass_place')->updateOrInsert(
                    [
                        'community_id' => $community->id,
                        'place_id'     => $place->id,
                    ],
                    [
                        'is_primary' => $massPlaceName === 'Nave',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
