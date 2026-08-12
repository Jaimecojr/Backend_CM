<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin Setting configurado: enviarNotificacionWA() falla temprano
        // por configuración incompleta, sin necesidad de Http::fake() en
        // los tests que no verifican el envío de WhatsApp explícitamente.
    }

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

        $response->assertStatus(422);
    }

    public function test_index_normaliza_owner_segun_type(): void
    {
        $user = User::factory()->create();
        Appointment::factory()->create(['type' => 1, 'user_id' => $user->id]);
        Appointment::factory()->create(['type' => 2, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/appointments?period=all');

        $response->assertStatus(200);
        $owners = array_column($response->json('data'), 'owner');
        $this->assertContains('affiliate', $owners);
        $this->assertContains('beneficiary', $owners);
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
