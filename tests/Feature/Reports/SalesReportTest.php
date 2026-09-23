<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Renovation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_afiliado_nuevo_sin_renovaciones_se_clasifica_como_nuevo(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create([
            'payment_date' => Carbon::today()->toDateString(),
            'value'        => 150000,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.tipo_venta', 'Nuevo')
                 ->assertJsonPath('data.0.valor_venta', 150000);
    }

    public function test_afiliado_con_varias_renovaciones_toma_solo_la_ultima(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create([
            'payment_date' => Carbon::today()->toDateString(),
            'value'        => 100000,
        ]);

        Renovation::factory()->create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2024-01-01',
            'value'        => 90000,
        ]);
        Renovation::factory()->create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2026-01-01',
            'value'        => 130000,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.tipo_venta', 'Renovación')
                 ->assertJsonPath('data.0.fecha_desde', '2026-01-01')
                 ->assertJsonPath('data.0.valor_venta', 130000);
    }

    public function test_totales_se_calculan_sobre_todo_el_filtro_no_la_pagina(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        Affiliate::factory()->count(3)->create(['payment_date' => Carbon::today()->toDateString(), 'value' => 100000]);
        $renewed = Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'value' => 100000]);
        Renovation::factory()->create(['affiliate_id' => $renewed->id, 'value' => 200000]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales?per_page=1');

        $response->assertStatus(200)
                 ->assertJsonPath('totals.new_count', 3)
                 ->assertJsonPath('totals.new_value', 300000)
                 ->assertJsonPath('totals.renewal_count', 1)
                 ->assertJsonPath('totals.renewal_value', 200000)
                 ->assertJsonPath('meta.total', 4)
                 ->assertJsonCount(1, 'data');
    }

    public function test_franquicia_no_ve_ventas_de_otra_franquicia_aunque_envie_su_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/sales?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_super_admin_ve_ventas_de_todas_las_franquicias(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $other = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($other)->getJson('/api/reports/sales');

        $response->assertStatus(403);
    }

    public function test_per_page_all_sin_resultados_no_rompe_la_paginacion(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales?per_page=all');

        $response->assertStatus(200)
                 ->assertJsonPath('meta.total', 0)
                 ->assertJsonPath('meta.last_page', 1);
    }

    public function test_export_rol_no_autorizado_recibe_403(): void
    {
        $other = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($other)->getJson('/api/reports/sales/export');

        $response->assertStatus(403);
    }

    public function test_export_descarga_excel_con_el_mismo_filtro(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString()]);

        $response = $this->actingAs($admin)->get('/api/reports/sales/export');

        $response->assertStatus(200);
        Excel::assertDownloaded(
            'Reporte_Ventas_' . Carbon::today()->format('d-m-Y') . '.xlsx'
        );
    }
}
