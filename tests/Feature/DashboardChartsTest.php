<?php
namespace Tests\Feature;
use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_12_month_arrays(): void
    {
        $user = User::factory()->create(['type' => 3]);
        $response = $this->actingAs($user)->getJson('/api/dashboard/charts?year=' . now()->year);
        $response->assertStatus(200)
                 ->assertJsonCount(12, 'data.appointments_by_month')
                 ->assertJsonCount(12, 'data.affiliates_by_month');
    }

    public function test_non_admin_does_not_receive_by_franchise(): void
    {
        $user = User::factory()->create(['type' => 2]);
        $response = $this->actingAs($user)->getJson('/api/dashboard/charts');
        $response->assertStatus(200);
        $this->assertArrayNotHasKey('by_franchise', $response->json('data'));
    }

    public function test_admin_receives_by_franchise(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        User::factory()->create(['type' => 2, 'state' => 1]);
        $response = $this->actingAs($admin)->getJson('/api/dashboard/charts');
        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['by_franchise' => ['users', 'appointments_by_franchise', 'affiliates_by_franchise']]]);
    }

    public function test_non_admin_only_sees_own_data(): void
    {
        $userA = User::factory()->create(['type' => 2]);
        $userB = User::factory()->create(['type' => 2]);
        $year  = now()->year;
        Appointment::factory()->create(['date' => now()->toDateString(), 'user_id' => $userA->id]);
        Appointment::factory()->create(['date' => now()->toDateString(), 'user_id' => $userB->id]);
        $response = $this->actingAs($userA)->getJson("/api/dashboard/charts?year={$year}");
        $mes = (int) now()->format('n') - 1;
        $response->assertStatus(200)
                 ->assertJsonPath("data.appointments_by_month.{$mes}", 1);
    }

    public function test_by_franchise_atribuye_los_conteos_a_la_franquicia_y_mes_correctos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $franchiseA = User::factory()->create(['type' => 2, 'state' => 1]);
        $franchiseB = User::factory()->create(['type' => 2, 'state' => 1]);
        $year       = now()->year;

        // Franquicia A: 2 citas en enero, 1 afiliado en febrero
        Appointment::factory()->count(2)->create([
            'date' => Carbon::create($year, 1, 15)->toDateString(),
            'user_id' => $franchiseA->id,
        ]);
        Affiliate::factory()->create([
            'payment_date' => Carbon::create($year, 2, 1)->toDateString(),
            'user_id' => $franchiseA->id,
        ]);

        // Franquicia B: 1 cita en marzo, 3 afiliados en mayo
        Appointment::factory()->create([
            'date' => Carbon::create($year, 3, 10)->toDateString(),
            'user_id' => $franchiseB->id,
        ]);
        Affiliate::factory()->count(3)->create([
            'payment_date' => Carbon::create($year, 5, 1)->toDateString(),
            'user_id' => $franchiseB->id,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/dashboard/charts?year={$year}");
        $response->assertStatus(200);

        $byFranchise = $response->json('data.by_franchise');
        $userIds     = array_column($byFranchise['users'], 'id');
        $idxA        = array_search($franchiseA->id, $userIds);
        $idxB        = array_search($franchiseB->id, $userIds);

        $this->assertSame(2, $byFranchise['appointments_by_franchise'][$idxA][0]); // enero
        $this->assertSame(1, $byFranchise['affiliates_by_franchise'][$idxA][1]);   // febrero
        $this->assertSame(1, $byFranchise['appointments_by_franchise'][$idxB][2]); // marzo
        $this->assertSame(3, $byFranchise['affiliates_by_franchise'][$idxB][4]);   // mayo

        // Sin contaminación cruzada entre franquicias
        $this->assertSame(0, $byFranchise['appointments_by_franchise'][$idxB][0]);
        $this->assertSame(0, $byFranchise['affiliates_by_franchise'][$idxA][4]);
    }

    public function test_by_franchise_no_escala_con_la_cantidad_de_franquicias(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        User::factory()->count(2)->create(['type' => 2, 'state' => 1]);
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($admin)->getJson('/api/dashboard/charts');
        $queriesConDosFranquicias = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Creación de las franquicias adicionales fuera del query log: solo nos
        // interesa contar las queries de la petición al dashboard, no los inserts.
        User::factory()->count(6)->create(['type' => 2, 'state' => 1]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($admin)->getJson('/api/dashboard/charts');
        $queriesConOchoFranquicias = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        // Con el loop N+1 original, 6 franquicias más habrían agregado ~12 queries.
        // Con la versión agrupada, el número de queries no depende de cuántas franquicias haya.
        $this->assertSame(
            $queriesConDosFranquicias,
            $queriesConOchoFranquicias,
            "Con 2 franquicias hubo {$queriesConDosFranquicias} queries y con 8 hubo {$queriesConOchoFranquicias} — el número de queries no debería depender de la cantidad de franquicias."
        );
    }
}
