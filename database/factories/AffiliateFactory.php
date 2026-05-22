<?php

namespace Database\Factories;

use App\Models\Agreement;
use App\Models\City;
use App\Models\Counselor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Affiliate>
 */
class AffiliateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Crear departamento y ciudad si no existen
        $departmentId = DB::table('departments')->insertGetId([
            'name'       => fake()->state(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $city = City::firstOrCreate(
            ['name' => fake()->city()],
            ['department_id' => $departmentId]
        );

        // Crear usuario (requerido por counselor)
        $user = User::firstOrCreate(
            ['email' => 'test.affiliate@example.com'],
            [
                'nit'      => '1234567890',
                'name'     => 'Test User',
                'user'     => 'testuser',
                'password' => bcrypt('password'),
                'state'    => 1,
                'type'     => 1,
                'city_id'  => $city->id,
            ]
        );

        // Crear counselor
        $counselor = Counselor::firstOrCreate(
            ['email' => 'test.counselor@example.com'],
            [
                'name'           => 'Test',
                'lastname'       => 'Counselor',
                'id_card'        => '9876543210',
                'type_contra'    => 'Corretaje',
                'password'       => bcrypt('password'),
                'state'          => 1,
                'city_id'        => $city->id,
                'user_id'        => $user->id,
            ]
        );

        $agreement = Agreement::firstOrCreate(
            ['name' => 'Test Agreement'],
            ['state' => 1, 'amount' => 100000, 'city_id' => $city->id]
        );

        return [
            'counselor_id'       => $counselor->id,
            'contract_code'      => fake()->unique()->numerify('CT####'),
            'name'               => fake()->firstName(),
            'lastname'           => fake()->lastName(),
            'bithdate'           => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'id_card'            => fake()->unique()->numerify('#########'),
            'phone'              => fake()->numerify('60#######'),
            'movil'              => fake()->numerify('300#######'),
            'address'            => fake()->address(),
            'city_id'            => $city->id,
            'email'              => fake()->unique()->safeEmail(),
            'validity'           => Carbon::now()->subYear()->toDateString(),
            'value_sale'         => fake()->numberBetween(50000, 500000),
            'agreement_id'       => $agreement->id,
            'balance'            => fake()->numberBetween(0, 100000),
            'comission'          => fake()->numberBetween(0, 50000),
            'payment_commission' => fake()->randomElement(['si', 'no']),
            'company'            => fake()->company(),
            'photo'              => null,
            'photo_rename'       => null,
            'validity_end'       => Carbon::now()->addYear()->toDateString(),
            'stade'              => 1,
            'carnet'             => fake()->randomElement(['si', 'no']),
            'state'              => 1,
            'user_id'            => $user->id,
            'sale_date'          => Carbon::now()->subMonths(3)->toDateString(),
        ];
    }
}
