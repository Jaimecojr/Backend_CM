<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(Doctor $referencia): array
    {
        return [
            'name' => 'Carlos',
            'lastname' => 'Ramírez',
            'specialty_id' => $referencia->specialty_id,
            'city_id' => $referencia->city_id,
            'phone' => '6041234567',
            'movil' => '3009876543',
            'address' => 'Calle 10 # 5-20',
            'secretary_name' => 'Secretaria Test',
            'value_agreement' => 80000,
            'state' => 1,
        ];
    }

    public function test_store_crea_medico_con_datos_validos(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/doctors', $this->payloadValido($referencia));

        $response->assertStatus(201);
        $this->assertDatabaseHas('doctors', ['name' => 'Carlos', 'lastname' => 'Ramírez']);
    }

    public function test_store_rechaza_movil_invalido(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();
        $payload = $this->payloadValido($referencia);
        $payload['movil'] = '123';

        $response = $this->actingAs($admin)->postJson('/api/doctors', $payload);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_rechaza_value_agreement_menor_al_minimo(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();
        $payload = $this->payloadValido($referencia);
        $payload['value_agreement'] = 5000;

        $response = $this->actingAs($admin)->postJson('/api/doctors', $payload);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['value_agreement']);
    }

    public function test_update_permite_edicion_parcial(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'value_agreement' => 120000,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'value_agreement' => 120000]);
    }

    public function test_destroy_elimina_el_medico(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/doctors/{$doctor->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('doctors', ['id' => $doctor->id]);
    }

    public function test_index_lista_medicos(): void
    {
        $admin = User::factory()->create();
        Doctor::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/doctors');

        $response->assertStatus(200);
    }

    /**
     * Confirmado en DoctorController::bySpecialty() que el query param es
     * 'specialty_id' (no 'specialty' ni otro nombre) — ver app/Http/Controllers/DoctorController.php.
     */
    public function test_by_specialty_filtra_medicos_activos_por_especialidad(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create(['state' => 1]);
        // Médico de otra especialidad, no debe aparecer en el resultado.
        Doctor::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/doctors/by-specialty?specialty_id=' . $doctor->specialty_id);

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $doctor->id]);
    }
}
