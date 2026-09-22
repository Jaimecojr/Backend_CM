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
}
