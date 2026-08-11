<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMovilValidationTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(User $referencia, string $movil): array
    {
        return [
            'nit'      => '9999999999',
            'name'     => 'Franquicia Test',
            'email'    => 'franquicia-test@example.com',
            'user'     => 'franquiciatest',
            'password' => 'secret123',
            'city_id'  => $referencia->city_id,
            'movil'    => $movil,
        ];
    }

    public function test_store_rechaza_movil_no_numerico(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = User::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/users',
            $this->payloadValido($referencia, 'no-es-un-numero'),
        );

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_acepta_movil_de_10_digitos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = User::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/users',
            $this->payloadValido($referencia, '3001234567'),
        );

        $response->assertStatus(201);
    }
}
