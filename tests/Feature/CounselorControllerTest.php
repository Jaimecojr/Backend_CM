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

    public function test_check_id_card_detecta_duplicado_y_respeta_ignore_id(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');
        $idCard = $created->json('data.id_card');

        $duplicate = $this->actingAs($admin)->getJson("/api/counselors/check-id-card?id_card={$idCard}");
        $duplicate->assertStatus(200);
        $duplicate->assertJson(['exists' => true]);

        $ignored = $this->actingAs($admin)->getJson(
            "/api/counselors/check-id-card?id_card={$idCard}&ignore_id={$id}"
        );
        $ignored->assertStatus(200);
        $ignored->assertJson(['exists' => false]);
    }

    public function test_update_rechaza_name_vacio_enviado_explicitamente(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", [
            'name' => '',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_permite_limpiar_email_enviando_null(): void
    {
        $admin = User::factory()->create();
        $payload = $this->payloadValido();
        $payload['email'] = 'inicial@example.com';
        $created = $this->actingAs($admin)->postJson('/api/counselors', $payload);
        $id = $created->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", [
            'email' => null,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('counselors', ['id' => $id, 'email' => null]);
    }

    public function test_update_hashea_la_nueva_password(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", [
            'password' => 'nuevaClave123',
        ]);

        $response->assertStatus(200);
        $stored = \App\Models\Counselor::find($id)->password;
        $this->assertNotSame('nuevaClave123', $stored);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nuevaClave123', $stored));
    }

    public function test_update_con_payload_invalido_y_asesor_inexistente_retorna_400(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson('/api/counselors/999999', [
            'name' => '',
        ]);

        // Mismo orden que en Doctor: la validación del Form Request corre
        // antes de Counselor::find($id).
        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_limpia_phone_enviando_cadena_vacia(): void
    {
        $admin = User::factory()->create();
        $payload = $this->payloadValido();
        $payload['phone'] = '6041234567';
        $created = $this->actingAs($admin)->postJson('/api/counselors', $payload);
        $id = $created->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", [
            'phone' => '',
        ]);

        // El middleware ConvertEmptyStringsToNull (activo por defecto en
        // Laravel 11 vía statefulApi()) convierte '' a null antes de que la
        // request llegue al Form Request, así que 'phone' llega como null
        // ya presente en validated(). Con el update() anterior
        // ($request->filled('phone')), un valor vacío/null era ignorado y
        // el phone existente se conservaba; con validated(), la clave
        // presente se aplica sin condición y limpia el campo a null. Cambio
        // de comportamiento deliberado de la migración a validated().
        $response->assertStatus(200);
        $this->assertDatabaseHas('counselors', ['id' => $id, 'phone' => null]);
    }

    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create();
        $response = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'counselors',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_de_state_registra_action_type_e(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $this->actingAs($admin)->patchJson("/api/counselors/{$id}", ['state' => 2]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'counselors',
            'table_id'     => $id,
        ]);
    }

    public function test_update_sin_cambiar_state_registra_action_type_u(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $this->actingAs($admin)->patchJson("/api/counselors/{$id}", ['phone' => '6041112233']);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'counselors',
            'table_id'     => $id,
        ]);
    }
}
