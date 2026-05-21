<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Crear departamento y ciudad mínimos para satisfacer la FK city_id
        $departmentId = DB::table('departments')->insertGetId([
            'name'       => fake()->state(),
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
            'nit'               => fake()->unique()->numerify('##########'),
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'user'              => fake()->unique()->userName(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'state'             => 1,
            'city_id'           => $cityId,
            'type'              => 2,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
