<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Counselor>
 */
class CounselorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => fake()->firstName(),
            'lastname'       => fake()->lastName(),
            'id_card'        => fake()->unique()->numerify('#########'),
            'address'        => fake()->address(),
            'date_admission' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'type_contra'    => 'Corretaje',
            'email'          => fake()->unique()->safeEmail(),
            'password'       => bcrypt('password'),
            'phone'          => fake()->numerify('60#######'),
            'movil'          => fake()->numerify('300#######'),
            'state'          => 1,
            'city_id'        => City::factory(),
            'user_id'        => User::factory(),
        ];
    }
}
