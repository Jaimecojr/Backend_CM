<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\Beneficiary;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sin Setting configurado: enviarNotificacionWA() falla temprano por
 * configuración incompleta, sin necesidad de Http::fake() en los tests
 * que no verifican el envío de WhatsApp explícitamente.
 */
class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_cita_con_datos_validos(): void
    {
        $user   = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/appointments', [
            'afi_code'  => 123,
            'doctor_id' => $doctor->id,
            'date'      => now()->addDay()->toDateString(),
            'hour'      => '10:00',
            'address'   => 'Calle 1 # 2-3',
            'city_id'   => $doctor->city_id,
            'phone'     => '3001234567',
            'value'     => 100000,
            'type'      => 1,
            'name'      => 'Paciente Test',
            'user_id'   => $user->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', ['name' => 'Paciente Test']);
    }

    public function test_store_rechaza_datos_incompletos(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/appointments', [
            'name' => 'Sin los demás campos',
        ]);

        // Este endpoint conserva 422 (comportamiento original, default de
        // FormRequest sin override) porque tiene tráfico real desde
        // frontend-cm — a diferencia de Affiliate/User, no se alinea con la
        // convención de 400 del resto de la app. Ver StoreAppointmentRequest
        // y task-15-report.md.
        $response->assertStatus(422);
    }

    public function test_index_normaliza_owner_segun_type(): void
    {
        // `owner` no es un string ('affiliate'/'beneficiary') — es el objeto
        // relacionado completo (Affiliate o Beneficiary), tal como lo espera
        // el panel (frontend-cm/app/.../appointments: owner.id, owner.name,
        // owner.lastname, owner.id_card). Verificado contra
        // AppointmentController::index() (línea `$arr['owner'] = $appt->type
        // === 1 ? $appt->affiliate : $appt->beneficiary`).
        $user        = User::factory()->create();
        $affiliate   = Affiliate::factory()->create();
        $beneficiary = Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name'         => 'Beneficiario Test',
            'id_card'      => '888999000',
        ]);

        Appointment::factory()->create(['type' => 1, 'afi_code' => $affiliate->id, 'user_id' => $user->id]);
        Appointment::factory()->create(['type' => 2, 'afi_code' => $beneficiary->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/appointments?period=all');

        $response->assertStatus(200);

        $porTipo = collect($response->json('data'))->keyBy('type');

        $this->assertSame($affiliate->id, $porTipo[1]['owner']['id'] ?? null);
        $this->assertSame($beneficiary->id, $porTipo[2]['owner']['id'] ?? null);
    }

    public function test_index_no_admin_solo_ve_sus_propias_citas(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Appointment::factory()->create(['user_id' => $userA->id]);
        Appointment::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->getJson('/api/appointments?period=all');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_destroy_elimina_la_cita(): void
    {
        $user        = User::factory()->create();
        $appointment = Appointment::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }
}
