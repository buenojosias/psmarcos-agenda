<?php

namespace Database\Factories;

use App\Enums\GroupTypeEnum;
use App\Models\Community;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        $templates = [
            ['Pastoral da Acolhida', GroupTypeEnum::PASTORAL],
            ['Pastoral do Dízimo', GroupTypeEnum::PASTORAL],
            ['Equipe de Liturgia', GroupTypeEnum::PASTORAL],
            ['Grupo de Oração', GroupTypeEnum::GROUP],
            ['Grupo de Famílias', GroupTypeEnum::GROUP],
            ['Equipe de Festas', GroupTypeEnum::SERVICE],
            ['Ministros da Comunhão', GroupTypeEnum::MINISTRY],
            ['Curso de Formação', GroupTypeEnum::COURSE],
        ];

        [$baseName, $type] = fake()->randomElement($templates);
        $suffix = fake()->unique()->numberBetween(1, 9999);
        $name = $baseName.' '.$suffix;

        return [
            'community_id' => Community::query()->inRandomOrder()->value('id'),
            'name' => $name,
            'type' => $type,
            'slug' => Str::slug($name),
            'description' => 'Grupo pastoral com atividades regulares de formação, organização e serviço comunitário.',
            'logo' => null,
        ];
    }
}
