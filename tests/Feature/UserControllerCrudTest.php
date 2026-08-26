<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_franquicia_con_type_por_defecto(): void
    {
        $admin = User::factory()->create();
        $cityId = User::factory()->create()->city_id;

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'nit' => '9998887771',
            'name' => 'Franquicia Nueva',
            'email' => 'nueva-franquicia@example.com',
            'user' => 'franquicianueva',
            'password' => 'secret123',
            'city_id' => $cityId,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['user' => 'franquicianueva', 'type' => 2]);
    }

    public function test_store_rechaza_nit_duplicado(): void
    {
        $admin = User::factory()->create();
        $existing = User::factory()->create(['nit' => '1112223334']);

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'nit' => '1112223334',
            'name' => 'Otra',
            'email' => 'otra@example.com',
            'user' => 'otrafranquicia',
            'password' => 'secret123',
            'city_id' => $existing->city_id,
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['nit']);
    }

    public function test_store_rechaza_movil_invalido(): void
    {
        $admin = User::factory()->create();
        $cityId = User::factory()->create()->city_id;

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'nit' => '2223334445',
            'name' => 'Con Movil Malo',
            'email' => 'movilmalo@example.com',
            'user' => 'movilmalo',
            'password' => 'secret123',
            'city_id' => $cityId,
            'movil' => '123',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_update_permite_edicion_parcial(): void
    {
        $admin = User::factory()->create();
        $franchise = User::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/users/{$franchise->id}", [
            'name' => 'Nombre Editado',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $franchise->id, 'name' => 'Nombre Editado']);
    }

    public function test_destroy_elimina_la_franquicia(): void
    {
        $admin = User::factory()->create();
        $franchise = User::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/users/{$franchise->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $franchise->id]);
    }

    public function test_index_lista_franquicias(): void
    {
        $admin = User::factory()->create();
        User::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_active_franchises_retorna_solo_activas(): void
    {
        $admin = User::factory()->create();
        User::factory()->create(['state' => 1]);
        User::factory()->create(['state' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/users/active');

        $response->assertStatus(200);
    }
}
