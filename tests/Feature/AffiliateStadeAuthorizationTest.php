<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateStadeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_admin_no_puede_cambiar_stade(): void
    {
        $asesor = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($asesor)->patchJson("/api/affiliates/{$affiliate->id}", [
            'stade' => 2,
        ]);

        $response->assertStatus(403);
        $this->assertSame(1, $affiliate->fresh()->stade);
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
