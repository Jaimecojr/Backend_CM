<?php

namespace Database\Factories;

use App\Models\Affiliate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Renovation>
 */
class RenovationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'date_ini'     => Carbon::now()->toDateString(),
            'date_end'     => Carbon::now()->addYear()->toDateString(),
            'date_payment' => Carbon::now()->toDateString(),
            'value'        => fake()->numberBetween(50000, 300000),
        ];
    }
}
