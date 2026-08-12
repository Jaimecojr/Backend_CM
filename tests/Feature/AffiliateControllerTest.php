<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_afiliado_con_datos_validos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/affiliates', [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Nuevo',
            'lastname'           => 'Afiliado',
            'id_card'            => '111222333',
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
            'movil'              => '3001234567',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('affiliates', ['id_card' => '111222333']);
    }

    public function test_store_crea_beneficiarios_asociados(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/affiliates', [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Con',
            'lastname'           => 'Beneficiarios',
            'id_card'            => '444555666',
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
            'movil'              => '3009876543',
            'beneficiaries'      => [['name' => 'Hijo Test', 'id_card' => '999']],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('beneficiaries', ['name' => 'Hijo Test']);
    }

    public function test_store_rechaza_sin_movil(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/affiliates', [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Sin',
            'lastname'           => 'Movil',
            'id_card'            => '777888999',
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
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_update_no_reactiva_el_afiliado_solo_por_editarlo(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 2]);

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'name' => 'Nombre editado',
        ]);

        $response->assertStatus(200);
        $this->assertSame(2, $affiliate->fresh()->stade);
    }

    public function test_destroy_elimina_el_afiliado(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/affiliates/{$affiliate->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('affiliates', ['id' => $affiliate->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->deleteJson('/api/affiliates/999999');

        $response->assertStatus(404);
    }

    public function test_index_filtra_por_stade(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['stade' => 1]);
        Affiliate::factory()->create(['stade' => 2]);

        $response = $this->actingAs($admin)->getJson('/api/affiliates?stade=2');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(2, $data[0]['stade']);
    }
}
