<?php

namespace Tests\Feature;

use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipFormPublicTest extends TestCase
{
    use RefreshDatabase;

    private function cityId(): int
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'TestDept', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return City::create(['name' => 'TestCity', 'department_id' => $deptId])->id;
    }

    public function test_store_crea_membership_form_y_beneficiarios(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/affiliate-request', [
            'name'         => 'Juan',
            'lastname'     => 'Pérez',
            'document'     => '1234567890',
            'movil'        => '3001234567',
            'email'        => 'juan@example.com',
            'birth_date'   => '1990-05-15',
            'address'      => 'Calle 1 # 2-3',
            'city_id'      => $cityId,
            'advisor_name' => 'Carlos Asesor',
            'beneficiaries'=> [['full_name' => 'Ana Pérez']],
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Juan')
                 ->assertJsonPath('data.id_card', '1234567890')
                 ->assertJsonPath('data.state', 0);

        $this->assertDatabaseHas('membership_forms', [
            'name'    => 'Juan',
            'id_card' => '1234567890',
            'phone'   => '3001234567',
            'bithdate'=> '1990-05-15',
            'seller'  => 'Carlos Asesor',
            'state'   => 0,
        ]);

        $this->assertDatabaseHas('membership_form_beneficiaries', ['name' => 'Ana Pérez']);
    }

    public function test_store_falla_sin_campos_requeridos(): void
    {
        $response = $this->postJson('/api/public/affiliate-request', []);

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_falla_con_phone_invalido(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/affiliate-request', [
            'name'         => 'Juan',
            'lastname'     => 'Pérez',
            'document'     => '1234567890',
            'movil'        => '123',
            'email'        => 'juan@example.com',
            'address'      => 'Calle 1',
            'city_id'      => $cityId,
            'advisor_name' => 'Asesor',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.movil.0', fn($v) => str_contains($v, '10'));
    }

    public function test_store_sin_beneficiarios_es_valido(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/affiliate-request', [
            'name'         => 'María',
            'lastname'     => 'Gómez',
            'document'     => '9876543210',
            'movil'        => '3109876543',
            'email'        => 'maria@example.com',
            'address'      => 'Carrera 5 # 10',
            'city_id'      => $cityId,
            'advisor_name' => 'Asesor',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('membership_form_beneficiaries', 0);
    }
}
