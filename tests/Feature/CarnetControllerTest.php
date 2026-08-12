<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CarnetControllerTest extends TestCase
{
    use RefreshDatabase;

    private function crearSettingCompleto(): Setting
    {
        return Setting::create([
            'wa_api_version'      => 'v18.0',
            'wa_phone_number_id'  => '1234567890',
            'wa_bearer_token'     => 'token-de-prueba',
            'wa_template_name'    => 'carnet_afiliado',
        ]);
    }

    public function test_send_usa_codigo_de_idioma_es_co_en_la_plantilla(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test123']]], 200),
        ]);

        $this->crearSettingCompleto();
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/affiliates/{$affiliate->id}/carnet");

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return $request['template']['language']['code'] === 'es_CO';
        });
    }

    public function test_send_retorna_422_cuando_el_envio_falla(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'rechazado']], 400),
        ]);

        $this->crearSettingCompleto();
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/affiliates/{$affiliate->id}/carnet");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Envío fallido');
    }
}
