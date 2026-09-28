<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use App\Models\Community;
use App\Models\Event;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+2 months');
        $endsAt = (clone $startsAt)->modify('+'.fake()->randomElement([60, 90, 120, 150, 180]).' minutes');

        $templates = [
            ['Reunião de planejamento', 'Organização das próximas atividades', EventTypeEnum::MEETING],
            ['Formação de lideranças', 'Encontro de formação pastoral', EventTypeEnum::COURSE],
            ['Ensaio geral', 'Preparação para a próxima celebração', EventTypeEnum::REHEARSAL],
            ['Café comunitário', 'Momento de convivência e partilha', EventTypeEnum::FOOD],
            ['Confraternização do grupo', 'Integração dos participantes e famílias', EventTypeEnum::FELLOWSHIP],
            ['Festa da comunidade', 'Atividade comunitária aberta às famílias', EventTypeEnum::PARTY],
        ];

        [$name, $complement, $type] = fake()->randomElement($templates);

        return [
            'group_id' => Group::query()->inRandomOrder()->value('id'),
            'community_id' => Community::query()->inRandomOrder()->value('id'),
            'name' => $name,
            'complement' => $complement,
            'type' => $type,
            'recurrence_code' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => fake()->randomElement(EventStatusEnum::cases()),
            'is_external' => false,
            'is_public' => fake()->boolean(75),
            'advertisable' => fake()->boolean(35),
        ];
    }

    public function external(): static
    {
        return $this->state(fn (): array => [
            'community_id' => null,
            'is_external' => true,
            'name' => 'Atividade pastoral externa',
            'complement' => 'Atividade realizada fora das dependências da paróquia',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => EventStatusEnum::CONFIRMED,
        ]);
    }
}
