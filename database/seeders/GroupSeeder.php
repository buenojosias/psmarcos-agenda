<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\GroupTypeEnum;
use App\Models\Community;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $communities = Community::query()->get()->keyBy(fn (Community $community): string => strtoupper($community->abbreviation));

        $groupsByCommunity = [
            'MSM' => [
                ['Pastoral da Liturgia', null, GroupTypeEnum::PASTORAL],
                ['Pastoral da Comunicação', 'Pascom', GroupTypeEnum::PASTORAL],
                ['Pastoral Familiar', null, GroupTypeEnum::PASTORAL],
                ['Pastoral da Catequese', null, GroupTypeEnum::PASTORAL],
                ['Pastoral do Dízimo', null, GroupTypeEnum::PASTORAL],
                ['Pastoral da Acolhida', null, GroupTypeEnum::PASTORAL],
                ['Legião de Maria', 'L.M.', GroupTypeEnum::MOVEMENT],
                ['Movimento de Irmãos', 'MI', GroupTypeEnum::MOVEMENT],
                ['Ministros Extraordinários da Comunhão', 'MESCs', GroupTypeEnum::MINISTRY],
                ['Conselho Pastoral Paroquial', 'CPP', GroupTypeEnum::COUNCIL],
                ['Coral Doce Canto', 'Coral', GroupTypeEnum::GROUP],
                ['Grupo de Jovens São Marcos', 'JSM', GroupTypeEnum::GROUP],
                ['Equipe de Festas e Eventos', null, GroupTypeEnum::SERVICE],
                ['Escola da Fé', null, GroupTypeEnum::COURSE],
            ],
            'BGC' => [
                ['Grupo de Jovens do Beato', 'GJB', GroupTypeEnum::GROUP],
                ['Catequese do Beato', null, GroupTypeEnum::PASTORAL],
                ['Legião de Maria - Beato', null, GroupTypeEnum::MOVEMENT],
                ['Equipe de Liturgia - Beato', null, GroupTypeEnum::PASTORAL],
                ['Equipe de Festas - Beato', null, GroupTypeEnum::SERVICE],
            ],
            'NSM' => [
                ['Catequese da Misericórdia', null, GroupTypeEnum::PASTORAL],
                ['Legião de Maria - Misericórdia', null, GroupTypeEnum::MOVEMENT],
                ['Equipe de Liturgia - Misericórdia', null, GroupTypeEnum::PASTORAL],
                ['Grupo de Oração da Misericórdia', null, GroupTypeEnum::GROUP],
                ['Equipe de Festas - Misericórdia', null, GroupTypeEnum::SERVICE],
            ],
            'NSP' => [
                ['Catequese da Perseverança', null, GroupTypeEnum::PASTORAL],
                ['Equipe de Liturgia - Perseverança', null, GroupTypeEnum::PASTORAL],
                ['Grupo de Oração da Perseverança', null, GroupTypeEnum::GROUP],
                ['Equipe de Festas - Perseverança', null, GroupTypeEnum::SERVICE],
                ['Grupo de Famílias - Perseverança', null, GroupTypeEnum::GROUP],
            ],
            'SJN' => [
                ['Catequese São João Neumann', null, GroupTypeEnum::PASTORAL],
                ['Equipe de Liturgia - São João Neumann', null, GroupTypeEnum::PASTORAL],
                ['Grupo de Oração São João Neumann', null, GroupTypeEnum::GROUP],
                ['Equipe de Festas - São João Neumann', null, GroupTypeEnum::SERVICE],
                ['Grupo de Famílias São João Neumann', null, GroupTypeEnum::GROUP],
            ],
        ];

        $memberships = [
            'Pastoral da Comunicação' => [
                'josias@email.com' => false,
                'pascom@email.com' => true,
            ],
            'Conselho Pastoral Paroquial' => [
                'cpp@email.com' => true,
            ],
            'Coral Doce Canto' => [
                'josias@email.com' => true,
            ],
        ];

        foreach ($groupsByCommunity as $abbreviation => $groups) {
            $community = $communities->get($abbreviation);

            if (! $community) {
                continue;
            }

            foreach ($groups as [$name, $abbreviationValue, $type]) {
                $group = Group::updateOrCreate(
                    [
                        'community_id' => $community->id,
                        'slug'         => Str::slug($name),
                    ],
                    [
                        'name'         => $name,
                        'abbreviation' => $abbreviationValue,
                        'type'         => $type,
                        'description'  => $this->descriptionFor($name),
                    ]
                );

                foreach ($memberships[$name] ?? [] as $email => $isCoordinator) {
                    $user = User::query()->where('email', $email)->first();

                    if ($user) {
                        $group->users()->syncWithoutDetaching([
                            $user->id => ['is_coordinator' => $isCoordinator],
                        ]);
                    }
                }
            }
        }
    }

    private function descriptionFor(string $name): string
    {
        return match (true) {
            str_contains($name, 'Catequese') => 'Organiza encontros catequéticos, formações e celebrações da comunidade.',
            str_contains($name, 'Liturgia') => 'Prepara e organiza as celebrações litúrgicas da comunidade.',
            str_contains($name, 'Festas') => 'Apoia a organização de festas, confraternizações e eventos comunitários.',
            str_contains($name, 'Oração') => 'Promove encontros de oração, partilha e espiritualidade.',
            str_contains($name, 'Famílias') || str_contains($name, 'Familiar') => 'Promove encontros, formações e ações voltadas às famílias.',
            str_contains($name, 'Coral') => 'Realiza ensaios, formações musicais e participação em celebrações.',
            default => 'Grupo pastoral com atividades regulares de formação, organização e serviço comunitário.',
        };
    }
}
