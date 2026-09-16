<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearSetting(): Setting
    {
        return Setting::create([
            'wa_api_version' => 'v18.0',
            'wa_phone_number_id' => '1234567890',
            'wa_bearer_token' => 'token-inicial',
            'wa_template_name' => 'plantilla_inicial',
        ]);
    }

    public function test_index_retorna_404_sin_configuracion(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/settings');

        $response->assertStatus(404);
    }

    public function test_index_retorna_la_configuracion_existente(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $this->crearSetting();

        $response = $this->actingAs($admin)->getJson('/api/settings');

        $response->assertStatus(200);
    }

    public function test_index_rechaza_usuario_no_super_admin(): void
    {
        $user = User::factory()->create(['type' => 2]);
        $this->crearSetting();

        $response = $this->actingAs($user)->getJson('/api/settings');

        $response->assertStatus(403);
    }

    public function test_update_actualiza_la_configuracion(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $setting = $this->crearSetting();

        $response = $this->actingAs($admin)->patchJson("/api/settings/{$setting->id}", [
            'wa_api_version' => 'v19.0',
            'wa_phone_number_id' => '1234567890',
            'wa_bearer_token' => 'token-nuevo',
            'wa_template_name' => 'plantilla_inicial',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('settings', ['id' => $setting->id, 'wa_api_version' => 'v19.0']);
    }

    public function test_update_rechaza_bearer_token_vacio(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $setting = $this->crearSetting();

        $response = $this->actingAs($admin)->patchJson("/api/settings/{$setting->id}", [
            'wa_api_version' => 'v19.0',
            'wa_phone_number_id' => '1234567890',
            'wa_bearer_token' => '',
            'wa_template_name' => 'plantilla_inicial',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['wa_bearer_token']);
    }

    public function test_update_rechaza_usuario_no_super_admin(): void
    {
        $user = User::factory()->create(['type' => 2]);
        $setting = $this->crearSetting();

        $response = $this->actingAs($user)->patchJson("/api/settings/{$setting->id}", [
            'wa_api_version' => 'v19.0',
            'wa_phone_number_id' => '1234567890',
            'wa_bearer_token' => 'token-nuevo',
            'wa_template_name' => 'plantilla_inicial',
        ]);

        $response->assertStatus(403);
    }
}
