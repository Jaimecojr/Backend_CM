<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateRegistActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_no_registra_regist_action(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        // Mismo payload que test_store_crea_afiliado_con_datos_validos en
        // AffiliateControllerTest — un store() válido y completo.
        $this->actingAs($admin)->postJson('/api/affiliates', [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Nuevo',
            'lastname'           => 'Afiliado',
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
            'movil'              => '3001234567',
        ]);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_update_que_cambia_stade_registra_action_type_e(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", ['stade' => 2]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'affiliates',
            'table_id'     => $affiliate->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_sin_stade_no_registra_regist_action(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", ['name' => 'Nombre Editado']);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_asesor_no_puede_cambiar_stade_y_no_registra_regist_action(): void
    {
        $asesor = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        // El controlador descarta `stade` del payload para roles no super admin
        // (ver AffiliateController::update), así que el valor no cambia y
        // wasChanged('stade') es false — no hay nada que auditar.
        $this->actingAs($asesor)->patchJson("/api/affiliates/{$affiliate->id}", ['stade' => 2]);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_cron_de_vencimiento_no_registra_regist_action(): void
    {
        Affiliate::factory()->create([
            'stade' => 1,
            'validity_end' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('affiliates:update-expired');

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_renovacion_que_reactiva_no_registra_regist_action(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 2]);

        $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini'     => now()->toDateString(),
            'date_end'     => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value'        => 100000,
        ]);

        $this->assertDatabaseCount('regist_actions', 0);
    }
}
