<?php

namespace Tests\Feature;

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_especialidad(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => 'Cardiología']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('specialties', ['name' => 'CARDIOLOGÍA']);
    }

    public function test_store_rechaza_nombre_duplicado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        // SQLite's `unique` check is case-sensitive (unlike MySQL's utf8mb4_unicode_ci in production),
        // so the duplicate must be sent with the same casing the model already stored it as.
        $existing = Specialty::create(['name' => 'Pediatría', 'state' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => $existing->name]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_store_requiere_super_admin(): void
    {
        $asesor = User::factory()->create(['type' => 2]);

        $response = $this->actingAs($asesor)->postJson('/api/specialties', ['name' => 'Cardiología']);

        $response->assertStatus(403);
    }

    public function test_update_requiere_super_admin(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $specialty = Specialty::create(['name' => 'Neurología', 'state' => 1]);

        $asesor = User::factory()->create(['type' => 2]);
        $response = $this->actingAs($asesor)->putJson("/api/specialties/{$specialty->id}", ['name' => 'Neurología Pediátrica']);

        $response->assertStatus(403);
    }

    public function test_destroy_elimina_la_especialidad(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $specialty = Specialty::create(['name' => 'Dermatología', 'state' => 1]);

        $response = $this->actingAs($admin)->deleteJson("/api/specialties/{$specialty->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('specialties', ['id' => $specialty->id]);
    }

    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => 'Oncología']);
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'specialties',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_store_no_autorizado_no_registra_regist_action(): void
    {
        $asesor = User::factory()->create(['type' => 2]);

        $this->actingAs($asesor)->postJson('/api/specialties', ['name' => 'Oncología']);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_update_de_state_registra_action_type_e(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $specialty = Specialty::create(['name' => 'Urología', 'state' => 1]);

        $this->actingAs($admin)->putJson("/api/specialties/{$specialty->id}", ['state' => 0]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'specialties',
            'table_id'     => $specialty->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_de_nombre_sin_cambiar_state_registra_action_type_u(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $specialty = Specialty::create(['name' => 'Endocrinología', 'state' => 1]);

        $this->actingAs($admin)->putJson("/api/specialties/{$specialty->id}", ['name' => 'Endocrinología Clínica']);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'specialties',
            'table_id'     => $specialty->id,
        ]);
    }
}
