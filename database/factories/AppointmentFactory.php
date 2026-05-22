<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
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

        return [
            'afi_code'  => fake()->numberBetween(1, 9999),
            'doctor_id' => Doctor::factory(),
            'date'      => Carbon::today()->toDateString(),
            'hour'      => fake()->time('H:i'),
            'address'   => fake()->address(),
            'city_id'   => $cityId,
            'phone'     => fake()->numerify('300#######'),
            'value'     => fake()->numberBetween(50000, 500000),
            'type'      => 1,
            'name'      => fake()->name(),
            'user_id'   => User::factory(),
        ];
    }
}
