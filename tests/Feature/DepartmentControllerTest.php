<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lista_departamentos(): void
    {
        // Nota: crear el $admin vía UserFactory inserta también un departamento adicional
        // (con nombre aleatorio de Faker, ej. "Maine") como efecto secundario para satisfacer
        // la FK city_id del usuario. Por eso no se puede asumir un total exacto de 2
        // departamentos en la respuesta — se verifica en cambio que los 2 departamentos
        // creados explícitamente en este test estén presentes.
        $admin = User::factory()->create();
        Department::create(['name' => 'Antioquia']);
        Department::create(['name' => 'Valle del Cauca']);

        $response = $this->actingAs($admin)->getJson('/api/departments');

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Antioquia']);
        $response->assertJsonFragment(['name' => 'Valle del Cauca']);

        $nombres = collect($response->json('data'))->pluck('name');
        $this->assertCount(2, $nombres->intersect(['Antioquia', 'Valle del Cauca']));
    }
}
