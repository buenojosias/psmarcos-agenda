<?php

namespace Database\Seeders;

use App\Models\Community;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CommunitySeeder extends Seeder
{
    public function run(): void
    {
        $communities = [
            [
                'name' => 'Matriz São Marcos',
                'abbreviation' => 'msm',
                'alias' => 'Matriz',
                'address' => 'Rua Roberto Gava, 310',
            ],
            [
                'name' => 'Capela Beato Giacomo Cusmano',
                'abbreviation' => 'bgc',
                'alias' => 'Beato',
                'address' => 'Rua Victório Gabardo, 325 (Bracatinga/Primavera)',
            ],
            [
                'name' => 'Capela Nossa Senhora da Misericórdia',
                'abbreviation' => 'nsm',
                'alias' => 'Misericórdia',
                'address' => 'Rua Campo Largo da Piedade, 462 (Vila Nori/Jardim Kosmos)',
            ],
            [
                'name' => 'Capela Nossa Senhora da Perseverança',
                'abbreviation' => 'nsp',
                'alias' => 'Perseverança',
                'address' => 'Rua Alexandre Von Humboldt, 283 (Próximo à Cruz do Pilarzinho)',
            ],
            [
                'name' => 'Capela Nossa Senhora do Pilar',
                'abbreviation' => 'pil',
                'alias' => 'Pilar',
                'address' => 'Rua São Salvador, 420',
            ],
            [
                'name' => 'Capela São João Neumann',
                'abbreviation' => 'sjn',
                'alias' => 'São João',
                'address' => 'Rua Ten. Miguel Anselmo da Silva, 485 (Vila dos Imigrantes)',
            ]
        ];

        Community::insert($communities);
    }
}
