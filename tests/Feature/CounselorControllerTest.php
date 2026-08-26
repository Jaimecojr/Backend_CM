<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(): array
    {
        $referencia = User::factory()->create();

        return [
            'name' => 'Laura',
            'lastname' => 'Gómez',
            'id_card' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'type_contra' => 'Término Fijo',
            'rol' => 1,
            'city_id' => $referencia->city_id,
            'user_id' => $referencia->id,
        ];
    }

    public function test_store_crea_asesor_con_datos_validos(): void
    {
        $admin = User::factory()->create();
        $payload = $this->payloadValido();

        $response = $this->actingAs($admin)->postJson('/api/counselors', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('counselors', ['id_card' => $payload['id_card']]);
    }

    public function test_store_rechaza_type_contra_invalido(): void
    {
        $admin = User::factory()->create();
        $payload = $this->payloadValido();
        $payload['type_contra'] = 'Tipo Inexistente';

        $response = $this->actingAs($admin)->postJson('/api/counselors', $payload);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['type_contra']);
    }

    public function test_store_rechaza_id_card_duplicada(): void
    {
        $admin = User::factory()->create();
        $primero = $this->payloadValido();
        $this->actingAs($admin)->postJson('/api/counselors', $primero);

        $segundo = $this->payloadValido();
        $segundo['id_card'] = $primero['id_card'];

        $response = $this->actingAs($admin)->postJson('/api/counselors', $segundo);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['id_card']);
    }

    public function test_update_y_destroy_sobre_asesor_creado(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $update = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", ['phone' => '6041112233']);
        $update->assertStatus(200);

        $destroy = $this->actingAs($admin)->deleteJson("/api/counselors/{$id}");
        $destroy->assertStatus(200);
        $this->assertDatabaseMissing('counselors', ['id' => $id]);
    }

    public function test_active_counselors_y_check_id_card(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $idCard = $created->json('data.id_card');

        $active = $this->actingAs($admin)->getJson('/api/counselors/active');
        $active->assertStatus(200);

        $check = $this->actingAs($admin)->getJson("/api/counselors/check-id-card?id_card={$idCard}");
        $check->assertStatus(200);
    }
}
