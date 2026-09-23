<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\City>
 */
class CityFactory extends Factory
{
    public function definition(): array
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name'       => fake()->unique()->state(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'department_id' => $departmentId,
            'name'          => fake()->unique()->city(),
        ];
    }
}
