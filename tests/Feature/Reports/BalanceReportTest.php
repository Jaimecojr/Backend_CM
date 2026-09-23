<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Counselor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class BalanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_incluye_afiliados_con_saldo_mayor_a_cero(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['balance' => 50000]);
        Affiliate::factory()->create(['balance' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/reports/balance');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_total_de_saldo_se_calcula_sobre_todo_el_filtro(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['balance' => 30000]);
        Affiliate::factory()->create(['balance' => 20000]);

        $response = $this->actingAs($admin)->getJson('/api/reports/balance?per_page=1');

        $response->assertStatus(200)
                 ->assertJsonPath('total_balance', 50000)
                 ->assertJsonPath('meta.total', 2)
                 ->assertJsonCount(1, 'data');
    }

    public function test_franquicia_no_ve_cartera_de_otra_franquicia_aunque_envie_su_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['balance' => 10000, 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['balance' => 10000, 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/balance?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['balance' => 10000]);

        $response = $this->actingAs($admin)->get('/api/reports/balance/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Cartera_' . now()->format('d-m-Y') . '.xlsx');
    }
}
