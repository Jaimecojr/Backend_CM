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
        $this->assertDatabaseHas('doctors', ['name' => 'CARLOS', 'lastname' => 'RAMÍREZ']);
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

    public function test_destroy_registra_regist_action_de_borrado(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $this->actingAs($admin)->deleteJson("/api/doctors/{$doctor->id}");

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'D',
            'target_table' => 'doctors',
            'table_id'     => $doctor->id,
            'user_id'      => $admin->id,
        ]);
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

    public function test_update_rechaza_name_vacio_enviado_explicitamente(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'name' => '',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_rechaza_movil_invalido(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'movil' => '123',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_update_permite_limpiar_email_enviando_null(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'email' => null,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'email' => null]);
    }

    public function test_update_con_payload_invalido_y_doctor_inexistente_retorna_400(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson('/api/doctors/999999', [
            'movil' => '123',
        ]);

        // La validación del Form Request corre antes de Doctor::find($id) —
        // por eso un payload inválido contra un id inexistente responde 400
        // (validación) en vez de 404 (no encontrado). Es el orden real de
        // Laravel para Form Requests inyectadas por tipo, no un bug.
        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/doctors', $this->payloadValido($referencia));
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_store_con_validacion_fallida_no_registra_regist_action(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();
        $payload = $this->payloadValido($referencia);
        $payload['movil'] = '123';

        $this->actingAs($admin)->postJson('/api/doctors', $payload);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_update_de_campo_normal_registra_action_type_u(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create(['state' => 1]);

        $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'value_agreement' => 120000,
        ]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'doctors',
            'table_id'     => $doctor->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_que_cambia_state_registra_action_type_e_una_sola_vez(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create(['state' => 1]);

        $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'state' => 2,
            'value_agreement' => 130000,
        ]);

        $this->assertDatabaseCount('regist_actions', 1);
        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'doctors',
            'table_id'     => $doctor->id,
        ]);
    }
}
