<?php

namespace Database\Factories;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'name'         => fake()->firstName(),
            'id_card'      => fake()->unique()->numerify('#########'),
            'bithdate'     => fake()->dateTimeBetween('-30 years', '-1 years')->format('Y-m-d'),
        ];
    }
}
