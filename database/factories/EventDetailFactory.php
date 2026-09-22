<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventDetail> */
class EventDetailFactory extends Factory
{
    protected $model = EventDetail::class;

    public function definition(): array
    {
        $registrationRequired = fake()->boolean();

        return [
            'event_id'                   => Event::factory(),
            'description'                => fake()->optional(0.8)->paragraph(),
            'target_audience'            => fake()->optional(0.6)->randomElement(['Famílias', 'Jovens', 'Toda a comunidade', 'Coordenadores de pastorais']),
            'participation_instructions' => fake()->optional(0.5)->randomElement(['Chegar 15 minutos antes.', 'Trazer um prato para partilhar.', 'Levar caderno e caneta.']),
            'registration_required'      => $registrationRequired,
            'registration_url'           => $registrationRequired ? fake()->optional(0.7)->url() : null,
            'registration_deadline'      => null,
            'participation_cost'         => fake()->optional(0.5)->randomElement(['Gratuito', 'Contribuição voluntária', 'R$ 25,00', '1 kg de alimento não perecível']),
            'contact_name'               => fake()->optional(0.7)->name(),
            'contact_phone'              => fake()->optional(0.6)->numerify('(##) #####-####'),
            'external_location_name'     => null,
            'external_location_address'  => null,
            'external_location_url'      => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (EventDetail $detail): void {
            $event = $detail->event;

            if ($detail->registration_required && $detail->registration_deadline === null && fake()->boolean(70)) {
                $detail->registration_deadline = $event->starts_at->copy()->subDays(fake()->numberBetween(1, 7));
            }

            if ($event->is_external) {
                $detail->external_location_name ??= fake()->optional(0.8)->company();
                $detail->external_location_address ??= fake()->optional(0.6)->address();
                $detail->external_location_url ??= fake()->optional(0.4)->url();
            }
        });
    }
}
