<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateStadeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_admin_no_cambia_stade_pero_la_peticion_tiene_exito(): void
    {
        $asesor = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($asesor)->patchJson("/api/affiliates/{$affiliate->id}", [
            'stade' => 2,
        ]);

        // El campo `stade` se ignora silenciosamente para roles no super admin,
        // pero el resto de la actualización debe completarse igual (200), no
        // rechazarse con 403 — de lo contrario el flujo de renovación de
        // franquicias (type=2), que siempre envía `stade = 1`, se rompería.
        $response->assertStatus(200);
        $this->assertSame(1, $affiliate->fresh()->stade);
    }

    public function test_franquicia_no_cambia_stade_pero_la_peticion_tiene_exito(): void
    {
        $franquicia = User::factory()->create(['type' => 2]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($franquicia)->patchJson("/api/affiliates/{$affiliate->id}", [
            'stade' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertSame(1, $affiliate->fresh()->stade);
    }

    public function test_franquicia_puede_renovar_otros_campos_aunque_envie_stade(): void
    {
        $franquicia = User::factory()->create(['type' => 2]);
        $affiliate = Affiliate::factory()->create([
            'stade' => 2,
            'validity_end' => now()->subDay()->toDateString(),
        ]);

        $nuevaVigencia = now()->addYear()->toDateString();

        // Simula el flujo real de renovación del frontend: el payload siempre
        // incluye `stade = 1` junto con la nueva `validity_end`.
        $response = $this->actingAs($franquicia)->patchJson("/api/affiliates/{$affiliate->id}", [
            'stade' => 1,
            'validity_end' => $nuevaVigencia,
        ]);

        $response->assertStatus(200);

        $fresh = $affiliate->fresh();
        $this->assertSame(2, $fresh->stade);
        $this->assertSame($nuevaVigencia, (string) $fresh->validity_end);
    }

    public function test_super_admin_si_puede_cambiar_stade(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'stade' => 2,
        ]);

        $response->assertStatus(200);
        $this->assertSame(2, $affiliate->fresh()->stade);
    }

    public function test_no_admin_puede_editar_otros_campos_sin_enviar_stade(): void
    {
        $asesor = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($asesor)->patchJson("/api/affiliates/{$affiliate->id}", [
            'name' => 'Nombre editado',
        ]);

        $response->assertStatus(200);
    }
}
