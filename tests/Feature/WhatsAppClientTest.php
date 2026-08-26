<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhatsappMessage;
use App\Services\WhatsAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppClientTest extends TestCase
{
    use RefreshDatabase;

    private function crearSettingCompleto(): void
    {
        Setting::create([
            'wa_api_version'     => 'v18.0',
            'wa_phone_number_id' => '1234567890',
            'wa_bearer_token'    => 'token-de-prueba',
            'wa_template_name'   => 'plantilla_test',
        ]);
    }

    public function test_enviar_plantilla_usa_es_co_y_registra_el_mensaje(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.abc']]], 200),
        ]);
        $this->crearSettingCompleto();

        $resultado = (new WhatsAppClient())->sendTemplate(
            '3001234567',
            'plantilla_test',
            [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Hola']]]],
            'carnet',
        );

        $this->assertTrue($resultado['enviado']);

        Http::assertSent(fn ($request) => $request['template']['language']['code'] === 'es_CO');

        $this->assertDatabaseHas('whatsapp_messages', [
            'recipient_id' => '573001234567',
            'type'         => 'carnet',
        ]);
    }

    public function test_enviar_plantilla_falla_con_configuracion_incompleta(): void
    {
        // Sin Setting creado.
        $resultado = (new WhatsAppClient())->sendTemplate(
            '3001234567',
            'plantilla_test',
            [],
            'carnet',
        );

        $this->assertFalse($resultado['enviado']);
        $this->assertSame('Configuración de WhatsApp incompleta', $resultado['detalle'] ?? null);
    }

    public function test_enviar_plantilla_maneja_error_de_red(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('timeout');
        });
        $this->crearSettingCompleto();

        $resultado = (new WhatsAppClient())->sendTemplate(
            '3001234567',
            'plantilla_test',
            [],
            'carnet',
        );

        $this->assertFalse($resultado['enviado']);

        $this->assertDatabaseHas('whatsapp_messages', [
            'recipient_id' => '573001234567',
            'type'         => 'carnet',
        ]);
    }
}
