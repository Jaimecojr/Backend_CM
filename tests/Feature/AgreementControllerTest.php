<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(): array
    {
        return [
            'name' => 'Convenio Test',
            'amount' => 150000,
            'state' => 1,
            'city_id' => User::factory()->create()->city_id,
        ];
    }

    public function test_store_requiere_super_admin(): void
    {
        $asesor = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($asesor)->postJson('/api/agreements', $this->payloadValido());

        $response->assertStatus(403);
    }

    public function test_store_crea_convenio_siendo_super_admin(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());

        $response->assertStatus(201);
        $this->assertDatabaseHas('agreements', ['name' => 'Convenio Test']);
    }

    public function test_update_requiere_super_admin(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $asesor = User::factory()->create(['type' => 3]);
        $response = $this->actingAs($asesor)->putJson("/api/agreements/{$id}", $this->payloadValido());

        $response->assertStatus(403);
    }

    public function test_destroy_no_requiere_super_admin(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $asesor = User::factory()->create(['type' => 3]);
        $response = $this->actingAs($asesor)->deleteJson("/api/agreements/{$id}");

        $response->assertStatus(200);
    }

    public function test_active_agreements_retorna_solo_activos(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $inactivo = $this->payloadValido();
        $inactivo['state'] = 0;
        $this->actingAs($admin)->postJson('/api/agreements', $inactivo);

        $response = $this->actingAs($admin)->getJson('/api/agreements/active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
