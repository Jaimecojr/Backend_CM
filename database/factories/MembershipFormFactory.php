<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class MembershipFormFactory extends Factory
{
    public function definition(): array
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name'       => fake()->state(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $city = City::firstOrCreate(
            ['name' => fake()->city()],
            ['department_id' => $departmentId]
        );

        return [
            'name'     => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'id_card'  => fake()->unique()->numerify('#########'),
            'phone'    => fake()->numerify('300#######'),
            'email'    => fake()->unique()->safeEmail(),
            'bithdate' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'address'  => fake()->address(),
            'city_id'  => $city->id,
            'date'     => now()->toDateString(),
            'seller'   => fake()->name(),
            'state'    => 0,
        ];
    }
}
