<?php

namespace Tests\Feature\Reports;

use App\Models\Counselor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_ve_asesores_de_cualquier_franquicia(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Counselor::factory()->create(['name' => 'Ana', 'lastname' => 'Gómez', 'state' => 1, 'user_id' => $franchiseA->id]);
        Counselor::factory()->create(['name' => 'Beto', 'lastname' => 'Ruiz', 'state' => 1, 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($admin)->getJson('/api/reports/catalogs/counselors');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_franquicia_solo_ve_sus_propios_asesores(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Counselor::factory()->create(['name' => 'Ana', 'lastname' => 'Gómez', 'state' => 1, 'user_id' => $franchiseA->id]);
        Counselor::factory()->create(['name' => 'Beto', 'lastname' => 'Ruiz', 'state' => 1, 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)->getJson('/api/reports/catalogs/counselors');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'ANA');
    }

    public function test_busqueda_requiere_minimo_dos_caracteres(): void
    {
        $franchise = User::factory()->create(['type' => 2]);
        Counselor::factory()->create(['name' => 'Ana', 'state' => 1, 'user_id' => $franchise->id]);

        $response = $this->actingAs($franchise)->getJson('/api/reports/catalogs/counselors?search=a');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $other = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($other)->getJson('/api/reports/catalogs/counselors');

        $response->assertStatus(403);
    }
}
