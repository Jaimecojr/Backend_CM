<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    public function definition(): array
    {
        // Crear departamento y ciudad mínimos para satisfacer la FK city_id
        $departmentId = DB::table('departments')->insertGetId([
            'name'       => fake()->unique()->state(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cityId = DB::table('cities')->insertGetId([
            'department_id' => $departmentId,
            'name'          => fake()->city(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Crear especialidad mínima para satisfacer la FK specialty_id
        $specialtyId = DB::table('specialties')->insertGetId([
            'name'       => fake()->word(),
            'state'      => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'specialty_id'    => $specialtyId,
            'name'            => fake()->firstName(),
            'lastname'        => fake()->lastName(),
            'phone'           => fake()->numerify('60#######'),
            'movil'           => fake()->numerify('300#######'),
            'address'         => fake()->address(),
            'secretary_name'  => fake()->name(),
            'value_agreement' => fake()->numberBetween(50000, 500000),
            'state'           => 1,
            'city_id'         => $cityId,
        ];
    }
}
