<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateMovilValidationTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(Affiliate $referencia, string $movil): array
    {
        return [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Juan',
            'lastname'           => 'Pérez',
            'id_card'            => '999888777',
            'city_id'            => $referencia->city_id,
            'validity'           => now()->toDateString(),
            'agreement_id'       => $referencia->agreement_id,
            'validity_end'       => now()->addYear()->toDateString(),
            'carnet'             => 'no',
            'state'              => 1,
            'user_id'            => $referencia->user_id,
            'payment_date'       => now()->toDateString(),
            'value'              => 100000,
            'balance'            => 0,
            'commission'         => 0,
            'payment_commission' => 'no',
            'movil'              => $movil,
        ];
    }

    public function test_store_rechaza_movil_no_numerico(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/affiliates',
            $this->payloadValido($referencia, 'no-es-un-numero'),
        );

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_rechaza_movil_con_menos_de_10_digitos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/affiliates',
            $this->payloadValido($referencia, '30012345'),
        );

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_acepta_movil_de_10_digitos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/affiliates',
            $this->payloadValido($referencia, '3001234567'),
        );

        $response->assertStatus(201);
    }
}
