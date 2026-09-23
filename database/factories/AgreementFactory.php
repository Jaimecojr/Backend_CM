<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agreement>
 */
class AgreementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => fake()->unique()->words(2, true),
            'amount'   => fake()->numberBetween(50000, 300000),
            'state'    => 1,
            'city_id'  => City::factory(),
        ];
    }
}
