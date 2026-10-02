<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    private const VERIFY_TOKEN = 'test-webhook-token';
    private const APP_SECRET   = 'test-app-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.webhook_token', self::VERIFY_TOKEN);
        config()->set('services.whatsapp.app_secret', self::APP_SECRET);
    }

    public function test_verify_retorna_challenge_con_token_correcto(): void
    {
        $response = $this->getJson('/api/webhook/whatsapp?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => self::VERIFY_TOKEN,
            'hub_challenge'    => '987654321',
        ]));

        $response->assertStatus(200);
        $response->assertSee('987654321');
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
    }

    public function test_should_return_403_when_challenge_is_not_numeric(): void
    {
        $response = $this->getJson('/api/webhook/whatsapp?' . http_build_query([
            'hub_mode'         => 'subscribe',
            'hub_verify_token' => self::VERIFY_TOKEN,
            'hub_challenge'    => '<script>alert(1)</script>',
        ]));

        $response->assertStatus(403);
    }

    public function test_should_return_403_when_verify_token_is_not_configured(): void
    {
        config()->set('services.whatsapp.webhook_token', null);

        $response = $this->getJson('/api/webhook/whatsapp?' . http_build_query([
            'hub_mode'      => 'subscribe',
            'hub_challenge' => '987654321',
        ]));

        $response->assertStatus(403);
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

        $response = $this->postSigned($payload);

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

        $response = $this->postSigned($payload);

        $response->assertStatus(200);
        Http::assertNothingSent();
    }

    public function test_handle_retorna_200_cuando_no_hay_mensajes_en_el_payload(): void
    {
        Http::fake();

        $response = $this->postSigned([
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

        $response = $this->postSigned($payload);

        $response->assertStatus(200);
        Http::assertNothingSent();
    }

    public function test_should_return_403_and_not_reply_when_signature_is_missing(): void
    {
        Http::fake();

        $response = $this->postJson('/api/webhook/whatsapp', $this->buildPayload('text', '573001234567', 'Hola'));

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_should_return_403_when_signature_does_not_match(): void
    {
        Http::fake();

        $payload = $this->buildPayload('text', '573001234567', 'Hola');

        $response = $this->postJson('/api/webhook/whatsapp', $payload, [
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'otro-secreto'),
        ]);

        $response->assertStatus(403);
        Http::assertNothingSent();
    }

    public function test_should_return_403_when_app_secret_is_not_configured(): void
    {
        config()->set('services.whatsapp.app_secret', null);

        $response = $this->postSigned($this->buildPayload('text', '573001234567', 'Hola'));

        $response->assertStatus(403);
    }

    /**
     * Sends the payload as Meta does: raw JSON body signed with HMAC-SHA256 of the App Secret.
     */
    private function postSigned(array $payload)
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/webhook/whatsapp', [], [], [], [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_ACCEPT'              => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=' . hash_hmac('sha256', $body, self::APP_SECRET),
        ], $body);
    }

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
