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
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => 'Cardiología']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('specialties', ['name' => 'Cardiología']);
    }

    public function test_store_rechaza_nombre_duplicado(): void
    {
        $admin = User::factory()->create();
        Specialty::create(['name' => 'Pediatría', 'state' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => 'Pediatría']);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_destroy_elimina_la_especialidad(): void
    {
        $admin = User::factory()->create();
        $specialty = Specialty::create(['name' => 'Dermatología', 'state' => 1]);

        $response = $this->actingAs($admin)->deleteJson("/api/specialties/{$specialty->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('specialties', ['id' => $specialty->id]);
    }
}
