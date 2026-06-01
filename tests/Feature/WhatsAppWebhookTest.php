<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{

    // ─── verify() ────────────────────────────────────────────────

    public function test_verify_retorna_challenge_con_token_correcto(): void
    {
        $response = $this->getJson('/api/webhook/whatsapp?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'contactomedico_webhook_2026',
            'hub_challenge'    => '987654321',
        ]));

        $response->assertStatus(200);
        $response->assertSee('987654321');
    }

    public function test_verify_retorna_403_con_token_incorrecto(): void
    {
        $response = $this->getJson('/api/webhook/whatsapp?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => 'token_equivocado',
            'hub_challenge'    => '987654321',
        ]));

        $response->assertStatus(403);
    }

    public function test_verify_retorna_403_sin_parametros(): void
    {
        $response = $this->getJson('/api/webhook/whatsapp');

        $response->assertStatus(403);
    }

    // ─── handle() ────────────────────────────────────────────────

    public function test_handle_retorna_200_y_envía_autoreply_con_mensaje_texto(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test123']]], 200),
        ]);

        \App\Models\Setting::create([
            'wa_api_version'     => 'v18.0',
            'wa_phone_number_id' => '123456789',
            'wa_bearer_token'    => 'test_bearer_token',
            'wa_template_name'   => 'carnet_template',
        ]);

        $payload = $this->buildPayload('text', '573001234567', 'Hola, quiero info');

        $response = $this->postJson('/api/webhook/whatsapp', $payload);

        $response->assertStatus(200);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com') &&
                   $request['type'] === 'text' &&
                   $request['to'] === '573001234567';
        });
    }

    public function test_handle_retorna_200_sin_enviar_reply_para_mensajes_no_texto(): void
    {
        Http::fake();

        \App\Models\Setting::create([
            'wa_api_version'     => 'v18.0',
            'wa_phone_number_id' => '123456789',
            'wa_bearer_token'    => 'test_bearer_token',
            'wa_template_name'   => 'carnet_template',
        ]);

        $payload = $this->buildPayload('image', '573001234567');

        $response = $this->postJson('/api/webhook/whatsapp', $payload);

        $response->assertStatus(200);
        Http::assertNothingSent();
    }

    public function test_handle_retorna_200_cuando_no_hay_mensajes_en_el_payload(): void
    {
        Http::fake();

        $response = $this->postJson('/api/webhook/whatsapp', [
            'object' => 'whatsapp_business_account',
            'entry'  => [],
        ]);

        $response->assertStatus(200);
        Http::assertNothingSent();
    }

    public function test_handle_retorna_200_sin_settings_y_no_envia_reply(): void
    {
        Http::fake();

        $payload = $this->buildPayload('text', '573001234567', 'Hola');

        $response = $this->postJson('/api/webhook/whatsapp', $payload);

        $response->assertStatus(200);
        Http::assertNothingSent();
    }

    // ─── helpers ─────────────────────────────────────────────────

    private function buildPayload(string $type, string $from, string $text = ''): array
    {
        $message = ['from' => $from, 'id' => 'wamid.test', 'timestamp' => '1700000000', 'type' => $type];

        if ($type === 'text') {
            $message['text'] = ['body' => $text];
        }

        return [
            'object' => 'whatsapp_business_account',
            'entry'  => [[
                'id'      => 'entry_id',
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata'          => ['phone_number_id' => '123456789'],
                        'messages'          => [$message],
                    ],
                    'field' => 'messages',
                ]],
            ]],
        ];
    }
}
