<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'    => $this->faker->name(),
            'email'   => $this->faker->safeEmail(),
            'phone'   => $this->faker->numerify('##########'),
            'city_id' => 1,
            'subject' => $this->faker->randomElement([
                'Información sobre planes',
                'Soporte técnico',
                'Quejas y reclamos',
                'Solicitud de información',
                'Otro',
            ]),
            'comment' => $this->faker->paragraph(),
        ];
    }
}
