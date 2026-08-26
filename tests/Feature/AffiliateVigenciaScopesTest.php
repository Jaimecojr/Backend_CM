<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateVigenciaScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_activos_vencidos_retorna_solo_stade_1_con_validity_end_pasado(): void
    {
        $vencido = Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->subDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->addDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 2, 'validity_end' => now()->subDay()->toDateString()]);

        $resultado = Affiliate::activeExpired()->get();

        $this->assertCount(1, $resultado);
        $this->assertSame($vencido->id, $resultado->first()->id);
    }

    public function test_activos_vencen_hoy_retorna_solo_stade_1_con_validity_end_hoy(): void
    {
        $vencenHoy = Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->toDateString()]);
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->addDay()->toDateString()]);

        $resultado = Affiliate::activeExpiringToday()->get();

        $this->assertCount(1, $resultado);
        $this->assertSame($vencenHoy->id, $resultado->first()->id);
    }

    public function test_inactivos_por_vencimiento_retorna_solo_stade_2_con_validity_end_pasado(): void
    {
        $inactivoVencido = Affiliate::factory()->create(['stade' => 2, 'validity_end' => now()->subDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 2, 'validity_end' => now()->addDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->subDay()->toDateString()]);

        $resultado = Affiliate::inactiveByExpiry()->get();

        $this->assertCount(1, $resultado);
        $this->assertSame($inactivoVencido->id, $resultado->first()->id);
    }
}
