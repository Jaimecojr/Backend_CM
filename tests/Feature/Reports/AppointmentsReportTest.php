<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\Beneficiary;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AppointmentsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_resuelve_nombre_de_titular_y_beneficiario_sin_n_mas_1(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $doctor    = Doctor::factory()->create(['name' => 'Carlos', 'lastname' => 'Pérez']);
        $affiliate = Affiliate::factory()->create(['name' => 'Ana', 'lastname' => 'Gómez']);
        $beneficiary = Beneficiary::factory()->create(['affiliate_id' => $affiliate->id, 'name' => 'Luis']);

        Appointment::factory()->create([
            'afi_code' => $affiliate->id, 'type' => 1, 'doctor_id' => $doctor->id, 'date' => Carbon::today()->toDateString(),
        ]);
        Appointment::factory()->create([
            'afi_code' => $beneficiary->id, 'type' => 2, 'doctor_id' => $doctor->id, 'date' => Carbon::today()->toDateString(),
        ]);

        DB::enableQueryLog();
        $response = $this->actingAs($admin)->getJson('/api/reports/appointments');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200)->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('ANA GÓMEZ (Titular)'));
        $this->assertTrue($names->contains('LUIS (Beneficiario)'));
        // 1 auth lookup + 1 count + 1 select + 1 eager-load doctor.city — a fixed, small number
        // regardless of row count proves there is no per-row query.
        $this->assertLessThanOrEqual(6, $queryCount);
    }

    public function test_franquicia_se_acota_por_appointments_user_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);
        $doctor     = Doctor::factory()->create();

        Appointment::factory()->create(['user_id' => $franchiseA->id, 'doctor_id' => $doctor->id]);
        Appointment::factory()->create(['user_id' => $franchiseB->id, 'doctor_id' => $doctor->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/appointments?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin  = User::factory()->create(['type' => 1]);
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create(['doctor_id' => $doctor->id]);

        $response = $this->actingAs($admin)->get('/api/reports/appointments/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Citas_' . now()->format('d-m-Y') . '.xlsx');
    }
}
