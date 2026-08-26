<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Renovation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenovationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_renovacion_con_datos_validos(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('renovations', ['affiliate_id' => $affiliate->id, 'value' => 150000]);
    }

    public function test_store_rechaza_date_end_anterior_a_date_ini(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->subDay()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['date_end']);
    }

    public function test_store_reactiva_el_afiliado_si_estaba_inactivo(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create(['stade' => 2]);

        $response = $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, $affiliate->fresh()->stade);
    }

    public function test_store_no_toca_stade_si_el_afiliado_ya_estaba_activo(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $this->assertSame(1, $affiliate->fresh()->stade);
    }

    public function test_index_lista_renovaciones(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create();
        Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/renovations');

        $response->assertStatus(200);
    }

    public function test_show_retorna_la_renovacion(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create();
        $renovation = Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/renovations/{$renovation->id}");

        $response->assertStatus(200);
    }
}
