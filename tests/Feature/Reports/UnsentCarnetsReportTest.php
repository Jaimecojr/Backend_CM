<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class UnsentCarnetsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_57_mas_movil_matchea_al_afiliado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['movil' => '3001234567']);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573001234567',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_envio_exitoso_no_aparece_como_no_enviado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['movil' => '3009876543']);

        WhatsappMessage::factory()->create([
            'recipient_id' => '573009876543',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_prefijo_sin_afiliado_correspondiente_no_rompe_el_reporte(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '999999999999',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_type_cita_no_se_incluye(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['movil' => '3001112233']);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573001112233',
            'type'         => 'cita',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_rango_por_defecto_solo_incluye_el_mes_actual(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['movil' => '3004445566']);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573004445566',
            'type'         => 'carnet',
            'created_at'   => Carbon::now(),
        ]);
        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573004445566',
            'type'         => 'carnet',
            'created_at'   => Carbon::now()->subMonth(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_franchise_id_filtra_por_franquicia_del_afiliado(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['movil' => '3007778888', 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['movil' => '3009990000', 'user_id' => $franchiseB->id]);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573007778888',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);
        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573009990000',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/reports/unsent-carnets?franchise_id=' . $franchiseA->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_franquicia_recibe_403(): void
    {
        $franchise = User::factory()->create(['type' => 2]);

        $response = $this->actingAs($franchise)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(403);
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $other = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($other)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(403);
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['movil' => '3005554433']);
        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573005554433',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->get('/api/reports/unsent-carnets/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Carnets_No_Enviados_' . now()->format('d-m-Y') . '.xlsx');
    }
}
