<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_by_department_retorna_solo_las_ciudades_de_ese_departamento(): void
    {
        $admin = User::factory()->create();
        $deptA = Department::create(['name' => 'Antioquia']);
        $deptB = Department::create(['name' => 'Cundinamarca']);
        $cityA = City::create(['name' => 'Medellín', 'department_id' => $deptA->id]);
        City::create(['name' => 'Bogotá', 'department_id' => $deptB->id]);

        $response = $this->actingAs($admin)->getJson("/api/departments/{$deptA->id}/cities");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($cityA->id, $response->json('data.0.id'));
    }
}
