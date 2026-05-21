# WhatsApp Webhook Auto-Reply Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar un webhook para recibir mensajes entrantes de WhatsApp y responder automáticamente indicando que el número solo es informativo.

**Architecture:** Un controlador `WhatsAppWebhookController` con dos métodos públicos — `verify()` para el handshake con Meta y `handle()` para procesar mensajes entrantes. Las credenciales WA (token, phone_id, api_version) se leen desde `Setting::first()` igual que el resto del sistema. El verify token del webhook vive en `.env` como `WHATSAPP_WEBHOOK_TOKEN` porque es un secreto de infraestructura, no un dato operativo. Las rutas van en `routes/api.php` fuera del grupo `auth:sanctum`; no requieren ningún cambio CSRF porque las rutas API ya están excluidas del middleware `VerifyCsrfToken` por diseño de Laravel.

**Tech Stack:** Laravel 12, Http facade, PHPUnit (Feature tests)

---

## Mapa de archivos

| Acción | Archivo |
|--------|---------|
| Crear | `app/Http/Controllers/WhatsAppWebhookController.php` |
| Crear | `tests/Feature/WhatsAppWebhookTest.php` |
| Modificar | `routes/api.php` |
| Modificar | `.env` |
| Modificar | `.env.example` |

---

## Task 1: Variables de entorno

**Files:**
- Modify: `.env`
- Modify: `.env.example`

- [ ] **Step 1: Agregar la variable en `.env`**

Al final del archivo, agregar:

```
# WhatsApp Webhook
WHATSAPP_WEBHOOK_TOKEN=contactomedico_webhook_2026
```

- [ ] **Step 2: Documentar la variable en `.env.example`**

Al final del archivo, agregar:

```
# WhatsApp Webhook — token secreto que se registra también en Meta for Developers
WHATSAPP_WEBHOOK_TOKEN=
```

---

## Task 2: Tests de feature (escribir primero — TDD)

**Files:**
- Create: `tests/Feature/WhatsAppWebhookTest.php`

- [ ] **Step 1: Crear el archivo de tests**

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

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

        // Crear fila de settings requerida
        \App\Models\Setting::create([
            'wa_api_version'    => 'v18.0',
            'wa_phone_number_id' => '123456789',
            'wa_bearer_token'   => 'test_bearer_token',
            'wa_template_name'  => 'carnet_template',
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
            'wa_api_version'    => 'v18.0',
            'wa_phone_number_id' => '123456789',
            'wa_bearer_token'   => 'test_bearer_token',
            'wa_template_name'  => 'carnet_template',
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
```

- [ ] **Step 2: Correr los tests para confirmar que fallan (el controlador no existe aún)**

```bash
php artisan test tests/Feature/WhatsAppWebhookTest.php
```

Resultado esperado: todos fallan con error de ruta no encontrada (404) o clase no existente.

---

## Task 3: Controlador

**Files:**
- Create: `app/Http/Controllers/WhatsAppWebhookController.php`

- [ ] **Step 1: Crear el controlador**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === env('WHATSAPP_WEBHOOK_TOKEN')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        $body     = $request->all();
        $messages = $body['entry'][0]['changes'][0]['value']['messages'] ?? null;

        if (!$messages) {
            return response('OK', 200);
        }

        $message = $messages[0];

        // Solo responder a mensajes de texto para evitar loops con status/notificaciones
        if (($message['type'] ?? '') !== 'text') {
            return response('OK', 200);
        }

        $this->sendAutoReply($message['from']);

        return response('OK', 200);
    }

    private function sendAutoReply(string $phone): void
    {
        $setting = Setting::first();

        if (
            !$setting ||
            empty($setting->wa_api_version) ||
            empty($setting->wa_phone_number_id) ||
            empty($setting->wa_bearer_token)
        ) {
            return;
        }

        $texto = "Hola 👋 Gracias por comunicarte con Contacto Médico.\n\n"
               . "Esta línea es exclusiva para el envío de confirmaciones y documentos. "
               . "No cuenta con atención por este medio.\n\n"
               . "Para atención personalizada comunícate con tu sede más cercana 📞 "
               . "https://beacons.ai/contactomedicocolombia\n\n"
               . "¡Estamos para servirte! 🙂";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $texto],
        ];

        $apiUrl = "https://graph.facebook.com/{$setting->wa_api_version}/{$setting->wa_phone_number_id}/messages";

        try {
            $http = Http::withToken($setting->wa_bearer_token);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }
            $http->post($apiUrl, $payload);
        } catch (\Throwable) {
            // Silencioso — Meta ya recibió el 200, no reintentar
        }
    }
}
```

---

## Task 4: Rutas

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Agregar las rutas del webhook antes del grupo `auth:sanctum`**

En `routes/api.php`, agregar el import del controlador al bloque `use` existente:

```php
use App\Http\Controllers\WhatsAppWebhookController;
```

Luego, dentro del bloque `Route::prefix('public')->group(...)` existente — **no**, agregar un bloque separado **antes** del grupo `auth:sanctum`:

```php
// Webhook WhatsApp — público, Meta lo llama directamente sin autenticación
Route::prefix('webhook')->group(function () {
    Route::get('whatsapp',  [WhatsAppWebhookController::class, 'verify']);
    Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle']);
});
```

Posición exacta: después del bloque `Route::prefix('public')` y antes de `Route::middleware('auth:sanctum')`.

---

## Task 5: Correr los tests finales

- [ ] **Step 1: Ejecutar la suite completa del webhook**

```bash
php artisan test tests/Feature/WhatsAppWebhookTest.php --verbose
```

Resultado esperado: todos los tests pasan en verde.

- [ ] **Step 2: Verificar que los tests existentes no se rompieron**

```bash
php artisan test
```

Resultado esperado: todos en verde, sin regresiones.

---

## Qué configurar en Meta for Developers (manual — después del deploy)

Una vez deployado en el servidor de producción:

1. Ir a [developers.facebook.com](https://developers.facebook.com) → tu app → WhatsApp → Configuration
2. Sección **Webhooks** → Edit
3. **Callback URL:** `https://tu-dominio.com/api/webhook/whatsapp`
4. **Verify Token:** `contactomedico_webhook_2026`
5. Clic en **Verify and Save** — Meta llama al GET, tu servidor responde con el challenge ✓
6. Suscribir el campo **`messages`** en la sección de campos del webhook

---

## Self-Review

**Spec coverage:**
- ✅ Ruta GET verify con hub_mode/hub_verify_token/hub_challenge
- ✅ Ruta POST handle para mensajes entrantes
- ✅ Auto-reply solo a mensajes de texto (evitar loops)
- ✅ Siempre retorna HTTP 200 a Meta
- ✅ Lee credenciales WA desde `Setting::first()` (no env/config)
- ✅ `withoutVerifying()` en entorno local
- ✅ Verify token en `.env`
- ✅ Rutas fuera de `auth:sanctum`
- ✅ CSRF no aplica (las rutas API ya están excluidas por diseño de Laravel)
- ✅ No se toca lógica existente de carnets ni citas

**Notas de diseño:**
- `sendAutoReply` es silencioso si falla — Meta ya recibió el 200, y reintentar podría causar loops.
- No se registra en `whatsapp_messages` el auto-reply — no aporta valor de auditoría y generaría ruido en reportes futuros.
- El número entrante (`from`) que manda Meta ya incluye el código de país (ej: `573001234567`), no hay que prefijarlo.
