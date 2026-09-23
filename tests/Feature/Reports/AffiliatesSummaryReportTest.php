<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AffiliatesSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicadores_basicos(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $active   = Affiliate::factory()->create(['validity_end' => Carbon::tomorrow()->toDateString()]);
        $inactive = Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString()]);
        Beneficiary::factory()->count(2)->create(['affiliate_id' => $active->id]);
        Beneficiary::factory()->count(1)->create(['affiliate_id' => $inactive->id]);

        $response = $this->actingAs($admin)->getJson('/api/reports/affiliates-summary');

        $response->assertStatus(200)
                 ->assertJsonPath('data.titulares', 2)
                 ->assertJsonPath('data.titulares_activos', 1)
                 ->assertJsonPath('data.titulares_inactivos', 1)
                 ->assertJsonPath('data.beneficiarios', 3)
                 ->assertJsonPath('data.beneficiarios_activos', 2)
                 ->assertJsonPath('data.beneficiarios_inactivos', 1);
    }

    public function test_beneficiarios_de_franquicia_a_no_se_cuentan_en_b(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        $affiliateA = Affiliate::factory()->create(['user_id' => $franchiseA->id]);
        $affiliateB = Affiliate::factory()->create(['user_id' => $franchiseB->id]);
        Beneficiary::factory()->count(2)->create(['affiliate_id' => $affiliateA->id]);
        Beneficiary::factory()->count(5)->create(['affiliate_id' => $affiliateB->id]);

        $response = $this->actingAs($franchiseA)->getJson('/api/reports/affiliates-summary');

        $response->assertStatus(200)
                 ->assertJsonPath('data.titulares', 1)
                 ->assertJsonPath('data.beneficiarios', 2);
    }

    public function test_franquicia_no_ve_indicadores_de_otra_franquicia_aunque_envie_su_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/affiliates-summary?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonPath('data.titulares', 1);
    }

    public function test_filtro_de_fechas_solo_se_aplica_si_vienen_ambas(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['validity' => '2020-01-01']);
        Affiliate::factory()->create(['validity' => '2026-01-01']);

        $response = $this->actingAs($admin)
            ->getJson('/api/reports/affiliates-summary?from=2025-01-01');

        $response->assertStatus(200)->assertJsonPath('data.titulares', 2);

        $response = $this->actingAs($admin)
            ->getJson('/api/reports/affiliates-summary?from=2025-01-01&to=2026-12-31');

        $response->assertStatus(200)->assertJsonPath('data.titulares', 1);
    }

    public function test_export_descarga_excel_con_los_6_indicadores(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create();

        $response = $this->actingAs($admin)->get('/api/reports/affiliates-summary/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Resumen_Afiliados_' . now()->format('d-m-Y') . '.xlsx');
    }
}
