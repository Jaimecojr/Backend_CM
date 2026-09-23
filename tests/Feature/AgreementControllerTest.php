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
        $this->assertDatabaseHas('agreements', ['name' => 'CONVENIO TEST']);
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

    public function test_destroy_registra_regist_action_de_borrado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $asesor = User::factory()->create(['type' => 3]);
        $this->actingAs($asesor)->deleteJson("/api/agreements/{$id}");

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'D',
            'target_table' => 'agreements',
            'table_id'     => $id,
            'user_id'      => $asesor->id,
        ]);
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

    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'agreements',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_de_state_registra_action_type_e(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $payload = $this->payloadValido();
        $payload['state'] = 0;
        $this->actingAs($admin)->putJson("/api/agreements/{$id}", $payload);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'agreements',
            'table_id'     => $id,
        ]);
    }

    public function test_update_sin_cambiar_state_registra_action_type_u(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $payload = $this->payloadValido();
        $payload['name'] = 'Convenio Editado';
        $this->actingAs($admin)->putJson("/api/agreements/{$id}", $payload);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'agreements',
            'table_id'     => $id,
        ]);
    }
}
