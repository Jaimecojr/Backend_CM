<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class NonRenewedAffiliatesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_incluye_afiliados_ya_inactivados_por_el_cron(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        // stade = 2: ya lo pasó el cron affiliates:update-expired por estar vencido.
        Affiliate::factory()->create(['stade' => 2, 'validity_end' => Carbon::yesterday()->toDateString()]);
        // stade = 1, vence hoy: aún no le corrió el cron.
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => Carbon::today()->toDateString()]);
        // Vigente: no debe aparecer.
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => Carbon::tomorrow()->toDateString()]);

        $response = $this->actingAs($admin)->getJson('/api/reports/non-renewed-affiliates');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_franquicia_no_ve_los_de_otra_franquicia(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString(), 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString(), 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/non-renewed-affiliates?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString()]);

        $response = $this->actingAs($admin)->get('/api/reports/non-renewed-affiliates/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Sin_Renovacion_' . now()->format('d-m-Y') . '.xlsx');
    }
}
