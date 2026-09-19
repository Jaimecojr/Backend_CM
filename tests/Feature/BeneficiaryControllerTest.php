<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BeneficiaryController tiene ruta viva (apiResource completo,
 * routes/api.php:79) y no tenía ningún test directo. Esta suite también
 * sirve de red de seguridad para el fix de validated() de la Tarea 3 — se
 * escribe antes de tocar el controlador, así que primero fija el
 * comportamiento actual.
 */
class BeneficiaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_beneficiario_con_datos_validos(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/beneficiaries', [
            'affiliate_id' => $affiliate->id,
            'name' => 'Hijo de Prueba',
            'id_card' => '1122334455',
            'bithdate' => '2015-03-10',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('beneficiaries', [
            'affiliate_id' => $affiliate->id,
            'name' => 'HIJO DE PRUEBA',
            'id_card' => '1122334455',
        ]);
    }

    public function test_store_rechaza_affiliate_id_inexistente(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/beneficiaries', [
            'affiliate_id' => 999999,
            'name' => 'Sin Afiliado',
            'id_card' => '9988776655',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['affiliate_id']);
    }

    public function test_store_rechaza_name_vacio(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/beneficiaries', [
            'affiliate_id' => $affiliate->id,
            'id_card' => '1231231231',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_index_lista_beneficiarios(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'Beneficiario Uno',
            'id_card' => '1112223334',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/beneficiaries');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_retorna_el_beneficiario(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $beneficiary = Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'Beneficiario Dos',
            'id_card' => '2223334445',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/beneficiaries/{$beneficiary->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'BENEFICIARIO DOS']);
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/beneficiaries/999999');

        $response->assertStatus(404);
    }

    public function test_update_permite_edicion_parcial(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $beneficiary = Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'Nombre Original',
            'id_card' => '3334445556',
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/beneficiaries/{$beneficiary->id}", [
            'name' => 'Nombre Editado',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('beneficiaries', [
            'id' => $beneficiary->id,
            'name' => 'NOMBRE EDITADO',
            'id_card' => '3334445556',
        ]);
    }

    public function test_update_retorna_404_si_no_existe(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->patchJson('/api/beneficiaries/999999', [
            'name' => 'No Importa',
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_elimina_el_beneficiario(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $beneficiary = Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'A Eliminar',
            'id_card' => '4445556667',
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/beneficiaries/{$beneficiary->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('beneficiaries', ['id' => $beneficiary->id]);
    }
}
