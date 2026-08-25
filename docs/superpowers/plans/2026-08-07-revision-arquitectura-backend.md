# Revisión de Arquitectura Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolver el bug activo de envío de carnets (código de idioma incorrecto) y aplicar los hallazgos de mayor ROI del spec `2026-08-07-revision-arquitectura-backend-design.md`: eliminar duplicación de lógica de envío WhatsApp, alinear una regla de validación inconsistente con lo documentado en `CLAUDE.md`, centralizar la verificación de super admin, extraer un controlador de autenticación, nombrar la lógica de vigencia dispersa, y agregar tests Feature a los dos módulos de mayor riesgo de negocio que hoy no tienen ninguno.

**Architecture:** Los cambios son independientes entre sí (distintos archivos, distintas preocupaciones) salvo la cadena Task 4→5→6→7, donde primero se crea el servicio `WhatsAppClient` y luego se migra cada uno de los 3 controladores que hoy duplican esa lógica. El Task 1 (bug fix literal) se hace primero y de forma aislada porque tiene valor inmediato independiente del resto — no se bloquea esperando la extracción del servicio.

**Tech Stack:** Laravel 11, PHPUnit (`php artisan test`), `Http::fake()` para simular la Graph API de Meta sin red real.

## Global Constraints

- Comentarios, nombres de variables y mensajes de respuesta JSON en **español** (regla general del proyecto, `CLAUDE.md`).
- **No hacer `git commit` dentro de las tareas.** Cada tarea termina en un checkpoint de verificación (tests en verde). El usuario decide cuándo integrar, según `[[feedback_no_commits_until_ready]]`.
- No modificar `config/auth.php` (`providers.users.driver`) — sigue siendo `md5-eloquent`, documentado y fuera de alcance de este plan.
- No modificar el driver de cookies `auth_hint` ni el `middleware`/`proxy.ts` del frontend — ya resuelto en una sesión anterior, fuera de alcance.
- Todas las tareas que tocan HTTP externo (Meta Graph API) usan `Http::fake()` en los tests — **nunca** un test debe hacer una petición de red real.

## Fuera de alcance de este plan

- No se crean `FormRequest` classes de forma general (el spec explícitamente descarta esa introducción masiva) — la Task 2 corrige la regla de `movil` inline, sin cambiar el patrón `Validator::make()` existente.
- No se agregan tests Feature para `DoctorController`, `CounselorController` ni el resto de catálogos pequeños — el spec los consideró de bajo riesgo/beneficio comparado con Affiliate/Appointment (Tasks 10-11).
- No se toca `config/auth.php`, `config/cors.php`, ni el fix de `auth_hint`/N+1 del dashboard — ya resueltos en la sesión anterior.

---

### Task 1: Bug activo — código de idioma `es` → `es_CO` en envío de carnet

**Files:**
- Modify: `app/Http/Controllers/CarnetController.php:65`
- Test: `tests/Feature/CarnetControllerTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: ninguna interfaz nueva — es un fix de un literal string.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/CarnetControllerTest.php`:
```php
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
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `php artisan test --filter=test_send_usa_codigo_de_idioma_es_co_en_la_plantilla`
Expected: FAIL — la aserción de `Http::assertSent` falla porque el código real enviado es `'es'`, no `'es_CO'`.

- [ ] **Step 3: Aplicar el fix**

En `app/Http/Controllers/CarnetController.php:65`, cambiar:
```php
                'language'   => ['code' => 'es'],
```
por:
```php
                'language'   => ['code' => 'es_CO'],
```

- [ ] **Step 4: Correr el test y confirmar que pasa**

Run: `php artisan test --filter=test_send_usa_codigo_de_idioma_es_co_en_la_plantilla`
Expected: PASS

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests existentes siguen en verde.

**Checkpoint — no hacer commit.**

---

### Task 2: Alinear la validación de `movil` con `CLAUDE.md` en `AffiliateController` y `UserController`

**Files:**
- Modify: `app/Http/Controllers/AffiliateController.php:75,171`
- Modify: `app/Http/Controllers/UserController.php:42,119`
- Test: `tests/Feature/AffiliateMovilValidationTest.php` (crear)
- Test: `tests/Feature/UserMovilValidationTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: ninguna interfaz nueva — es un cambio de regla de validación.

**Contexto verificado:** `CLAUDE.md` documenta `movil` como `'nullable|digits:10'` en todo el proyecto. `CounselorController`, `DoctorController`, `ContactController` y `MembershipFormController` ya lo cumplen. `AffiliateController` y `UserController` usan `'nullable|string|max:50'` — el mismo campo que `CarnetController` usa para armar el número de WhatsApp del destinatario.

- [ ] **Step 1: Escribir los tests que fallan**

Crear `tests/Feature/AffiliateMovilValidationTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateMovilValidationTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(Affiliate $referencia, string $movil): array
    {
        return [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Juan',
            'lastname'           => 'Pérez',
            'id_card'            => '999888777',
            'city_id'            => $referencia->city_id,
            'validity'           => now()->toDateString(),
            'agreement_id'       => $referencia->agreement_id,
            'validity_end'       => now()->addYear()->toDateString(),
            'carnet'             => 'no',
            'state'              => 1,
            'user_id'            => $referencia->user_id,
            'payment_date'       => now()->toDateString(),
            'value'              => 100000,
            'balance'            => 0,
            'commission'         => 0,
            'payment_commission' => 'no',
            'movil'              => $movil,
        ];
    }

    public function test_store_rechaza_movil_no_numerico(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/affiliates',
            $this->payloadValido($referencia, 'no-es-un-numero'),
        );

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_rechaza_movil_con_menos_de_10_digitos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/affiliates',
            $this->payloadValido($referencia, '30012345'),
        );

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_acepta_movil_de_10_digitos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/affiliates',
            $this->payloadValido($referencia, '3001234567'),
        );

        $response->assertStatus(201);
    }
}
```

Crear `tests/Feature/UserMovilValidationTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMovilValidationTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(User $referencia, string $movil): array
    {
        return [
            'nit'      => '9999999999',
            'name'     => 'Franquicia Test',
            'email'    => 'franquicia-test@example.com',
            'user'     => 'franquiciatest',
            'password' => 'secret123',
            'city_id'  => $referencia->city_id,
            'movil'    => $movil,
        ];
    }

    public function test_store_rechaza_movil_no_numerico(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = User::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/users',
            $this->payloadValido($referencia, 'no-es-un-numero'),
        );

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_acepta_movil_de_10_digitos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = User::factory()->create();

        $response = $this->actingAs($admin)->postJson(
            '/api/users',
            $this->payloadValido($referencia, '3001234567'),
        );

        $response->assertStatus(201);
    }
}
```

- [ ] **Step 2: Correr los tests y confirmar que fallan**

Run: `php artisan test --filter=MovilValidationTest`
Expected: FAIL en `test_store_rechaza_movil_no_numerico` y `test_store_rechaza_movil_con_menos_de_10_digitos` de ambos archivos (hoy `'nullable|string|max:50'` acepta esos valores).

- [ ] **Step 3: Aplicar el fix en `AffiliateController`**

En `app/Http/Controllers/AffiliateController.php`, líneas 75 y 171, cambiar en ambos métodos (`store` y `update`):
```php
            'movil'              => 'nullable|string|max:50',
```
por:
```php
            'movil'              => 'nullable|digits:10',
```

- [ ] **Step 4: Aplicar el fix en `UserController`**

En `app/Http/Controllers/UserController.php`, líneas 42 y 119, cambiar en ambos métodos (`store` y `update`):
```php
            'movil' => 'nullable|string|max:50',
```
por:
```php
            'movil' => 'nullable|digits:10',
```

- [ ] **Step 5: Correr los tests y confirmar que pasan**

Run: `php artisan test --filter=MovilValidationTest`
Expected: PASS en los 5 tests nuevos.

- [ ] **Step 6: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests existentes siguen en verde. Si algún test o seed existente crea afiliados/usuarios con `movil` que no sean 10 dígitos numéricos (ej. `null` sigue permitido por `nullable`, pero un string con letras ya no), revisar ese caso puntual antes de continuar.

**Checkpoint — no hacer commit.**

---

### Task 3: `User::esSuperAdmin()` — método de dominio para reemplazar las 8 verificaciones inline de `type !== 1`

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Http/Controllers/DashboardController.php:15,62`
- Modify: `app/Http/Controllers/AffiliateController.php:268`
- Modify: `app/Http/Controllers/AffiliateNoteController.php:55`
- Modify: `app/Http/Controllers/AgreementController.php:38,95`
- Modify: `app/Http/Controllers/AppointmentController.php:30,196`
- Test: `tests/Feature/UserSuperAdminTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: `User::esSuperAdmin(): bool`, usado por las 8 verificaciones de los controladores listados arriba y disponible para cualquier controlador futuro.

**Contexto verificado — importante, no todas las 8 ocurrencias hacen lo mismo:**
- **4 son verificaciones de autorización** (si no es super admin, `return` 403 y no continúa): `DashboardController.php:15`, `AffiliateNoteController.php:55`, `AgreementController.php:38`, `AgreementController.php:95`.
- **4 son de *scoping* de query** (si no es super admin, se filtra el resultado a `user_id` del propio usuario — no se deniega nada): `AffiliateController.php:268`, `AppointmentController.php:30`, `AppointmentController.php:196`, `DashboardController.php:62`.

Ambos grupos preguntan lo mismo (`$user->type === 1`), así que ambos se benefician de un único método con nombre, aunque la consecuencia de cada llamado sea distinta. **No se debe convertir esto en un `Gate::define` con `abort(403)`** — eso solo encajaría en el primer grupo; el segundo grupo necesita el booleano crudo para decidir si filtra o no.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/UserSuperAdminTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_es_super_admin_es_verdadero_solo_para_type_1(): void
    {
        $admin    = User::factory()->create(['type' => 1]);
        $counselor = User::factory()->create(['type' => 2]);
        $advisor  = User::factory()->create(['type' => 3]);

        $this->assertTrue($admin->esSuperAdmin());
        $this->assertFalse($counselor->esSuperAdmin());
        $this->assertFalse($advisor->esSuperAdmin());
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `php artisan test --filter=test_es_super_admin_es_verdadero_solo_para_type_1`
Expected: FAIL con `Call to undefined method App\Models\User::esSuperAdmin()`.

- [ ] **Step 3: Agregar el método al modelo**

En `app/Models/User.php`, dentro de la clase, después de `username()` (línea 65):
```php
    // Super admin: único rol con acceso a métricas globales, gestión de
    // convenios/notas y sin restricción de "solo ver mis propios registros".
    public function esSuperAdmin(): bool
    {
        return $this->type === 1;
    }
```

- [ ] **Step 4: Correr el test y confirmar que pasa**

Run: `php artisan test --filter=test_es_super_admin_es_verdadero_solo_para_type_1`
Expected: PASS

- [ ] **Step 5: Reemplazar las 4 verificaciones de autorización**

En `app/Http/Controllers/DashboardController.php:15`:
```php
        if (auth()->user()->type !== 1) {
```
por:
```php
        if (!auth()->user()->esSuperAdmin()) {
```

En `app/Http/Controllers/AffiliateNoteController.php:55`:
```php
        if ($request->user()->type !== 1) {
```
por:
```php
        if (!$request->user()->esSuperAdmin()) {
```

En `app/Http/Controllers/AgreementController.php:38` y `:95` (ambas ocurrencias, mismo cambio):
```php
        if ($request->user()->type !== 1) {
```
por:
```php
        if (!$request->user()->esSuperAdmin()) {
```

- [ ] **Step 6: Reemplazar las 4 verificaciones de scoping**

En `app/Http/Controllers/AffiliateController.php:268`:
```php
        if (auth()->user()->type !== 1) {
```
por:
```php
        if (!auth()->user()->esSuperAdmin()) {
```

En `app/Http/Controllers/AppointmentController.php:30` y `:196` (ambas ocurrencias):
```php
        if (auth()->user()->type !== 1) {
```
por:
```php
        if (!auth()->user()->esSuperAdmin()) {
```

En `app/Http/Controllers/DashboardController.php:62`:
```php
        if ($user->type !== 1) {
```
por:
```php
        if (!$user->esSuperAdmin()) {
```

- [ ] **Step 7: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests existentes siguen en verde — este cambio es una sustitución 1:1 del mismo booleano, no cambia ningún comportamiento observable.

**Checkpoint — no hacer commit.**

---

### Task 4: Servicio `WhatsAppClient` — centralizar el envío de plantillas

**Files:**
- Create: `app/Services/WhatsAppClient.php`
- Test: `tests/Feature/WhatsAppClientTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas (Task 3 ya terminó; este servicio es independiente).
- Produce: `WhatsAppClient::enviarPlantilla(string $telefono, string $templateName, array $components, string $tipoRegistro): array` — retorna `['enviado' => bool, 'response' => array]`. Las Tasks 5 y 6 consumen este método.

**Contexto verificado:** `CarnetController::send()` y `AppointmentController::enviarNotificacionWA()` repiten idéntica secuencia: leer `Setting::first()`, validar 3 campos comunes (`wa_api_version`, `wa_phone_number_id`, `wa_bearer_token`) + el nombre de plantilla específico de cada uno, armar `payload` con `type: template` y `language.code: es_CO` (fijo — este servicio es lo que hace permanente el fix de la Task 1), llamar `Http::withToken(...)->withoutVerifying()` en local, y registrar en `whatsapp_messages`. El webhook (`sendAutoReply`) usa un payload de tipo `text` distinto y no registra en `whatsapp_messages` — **no** se migra en esta tarea (ver Task 7).

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/WhatsAppClientTest.php`:
```php
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

        $resultado = (new WhatsAppClient())->enviarPlantilla(
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
        $resultado = (new WhatsAppClient())->enviarPlantilla(
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

        $resultado = (new WhatsAppClient())->enviarPlantilla(
            '3001234567',
            'plantilla_test',
            [],
            'carnet',
        );

        $this->assertFalse($resultado['enviado']);
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `php artisan test --filter=WhatsAppClientTest`
Expected: FAIL con `Class "App\Services\WhatsAppClient" not found`.

- [ ] **Step 3: Crear el servicio**

Crear `app/Services/WhatsAppClient.php`:
```php
<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    /**
     * Envía un mensaje de plantilla (carnet, notificación de cita) y
     * registra el resultado en whatsapp_messages. El código de idioma es
     * siempre 'es_CO' — Meta rechaza 'es' con el error #132001.
     *
     * @param  array<int, array<string, mixed>>  $components  Componentes de la plantilla (header/body de Meta).
     * @return array{enviado: bool, response?: array, detalle?: string}
     */
    public function enviarPlantilla(string $telefono, string $templateName, array $components, string $tipoRegistro): array
    {
        $settings = Setting::first();

        if (
            !$settings ||
            empty($settings->wa_api_version) ||
            empty($settings->wa_phone_number_id) ||
            empty($settings->wa_bearer_token) ||
            empty($templateName)
        ) {
            return ['enviado' => false, 'detalle' => 'Configuración de WhatsApp incompleta'];
        }

        $recipient = '57' . $telefono;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $recipient,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => 'es_CO'],
                'components' => $components,
            ],
        ];

        $apiUrl = "https://graph.facebook.com/{$settings->wa_api_version}/{$settings->wa_phone_number_id}/messages";

        try {
            $http = Http::withToken($settings->wa_bearer_token);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }
            $response     = $http->post($apiUrl, $payload);
            $responseData = $response->json();
        } catch (\Throwable $e) {
            return ['enviado' => false, 'detalle' => 'Error al contactar la API de WhatsApp'];
        }

        WhatsappMessage::create([
            'response'     => json_encode($responseData),
            'recipient_id' => $recipient,
            'deleted'      => 0,
            'type'         => $tipoRegistro,
        ]);

        return [
            'enviado'  => !empty($responseData['messages'][0]['id']),
            'response' => $responseData,
        ];
    }
}
```

- [ ] **Step 4: Correr el test y confirmar que pasa**

Run: `php artisan test --filter=WhatsAppClientTest`
Expected: PASS en los 3 tests.

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests existentes siguen en verde (este servicio todavía no lo consume ningún controlador — eso es la Task 5).

**Checkpoint — no hacer commit.**

---

### Task 5: Migrar `CarnetController` a `WhatsAppClient`

**Files:**
- Modify: `app/Http/Controllers/CarnetController.php`
- Modify: `tests/Feature/CarnetControllerTest.php` (agregar caso)

**Interfaces:**
- Consume: `WhatsAppClient::enviarPlantilla()` de la Task 4.
- Produce: mismo comportamiento observable de `POST /api/affiliates/{id}/carnet` (mismo JSON de respuesta), ahora sin lógica de payload/HTTP propia.

**Contexto verificado:** después de esta migración, `CarnetController` deja de tener su propio `$payload`, su propia llamada `Http::`, y su propio `WhatsappMessage::create()` — todo eso vive en `WhatsAppClient`. El fix de la Task 1 (`es_CO`) ya no puede volver a "perderse" en este archivo porque el código del idioma ya no existe aquí.

- [ ] **Step 1: Escribir el test que falla (caso de configuración incompleta, para fijar el contrato de respuesta tras la migración)**

Agregar a `tests/Feature/CarnetControllerTest.php`:
```php
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
```

- [ ] **Step 2: Correr los tests y confirmar que el existente sigue en verde (referencia "antes" de tocar el controlador)**

Run: `php artisan test --filter=CarnetControllerTest`
Expected: el test nuevo (`test_send_retorna_422_cuando_el_envio_falla`) PASA ya con el código actual (todavía no migrado) — esto confirma el contrato de respuesta que la migración debe preservar.

- [ ] **Step 3: Migrar `CarnetController::send()` a usar el servicio**

En `app/Http/Controllers/CarnetController.php`, agregar el import y el constructor con inyección de dependencia:
```php
use App\Services\WhatsAppClient;
```
```php
class CarnetController extends Controller
{
    public function __construct(private WhatsAppClient $whatsapp)
    {
    }

    public function send($id)
    {
```

Reemplazar todo el bloque desde `$payload = [` (línea 58 original) hasta el final del método (línea 121 original) por:
```php
        $components = [
            [
                'type'       => 'header',
                'parameters' => [[
                    'type'     => 'document',
                    'document' => [
                        'link'     => $pdfUrl,
                        'filename' => 'carnet.pdf',
                    ],
                ]],
            ],
            [
                'type'       => 'body',
                'parameters' => [[
                    'type' => 'text',
                    'text' => $nombreCompleto,
                ]],
            ],
        ];

        $resultado = $this->whatsapp->enviarPlantilla(
            $affiliate->movil,
            $settings->wa_template_name,
            $components,
            'carnet',
        );

        if ($resultado['enviado']) {
            $affiliate->carnet = 'si';
            $affiliate->save();

            return response()->json([
                'message' => 'Carnet enviado exitosamente',
                'data'    => $resultado['response'],
            ], 200);
        }

        return response()->json([
            'message' => 'Envío fallido',
            'error'   => $resultado['response'] ?? $resultado['detalle'] ?? null,
        ], 422);
    }
```

(El resto del método `send()` — validaciones de `movil`/`settings`/`validity_end`, generación del PDF, cálculo de `$recipient`/`$pdfUrl`/`$nombreCompleto` — no cambia. Solo se elimina el bloque de armado de payload + llamada HTTP + registro en `whatsapp_messages`, que ahora vive en `WhatsAppClient`. También se elimina el `use Illuminate\Support\Facades\Http;` y `use App\Models\WhatsappMessage;` si ya no se usan en el resto del archivo — verificar con `grep -n "Http::\|WhatsappMessage::" app/Http/Controllers/CarnetController.php` antes de borrar los imports.)

- [ ] **Step 4: Correr los tests y confirmar que pasan**

Run: `php artisan test --filter=CarnetControllerTest`
Expected: PASS en los 2 tests (el de la Task 1 y el nuevo de esta tarea).

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests siguen en verde.

**Checkpoint — no hacer commit.**

---

### Task 6: Migrar `AppointmentController::enviarNotificacionWA` a `WhatsAppClient`

**Files:**
- Modify: `app/Http/Controllers/AppointmentController.php`
- Test: `tests/Feature/AppointmentWhatsAppNotificationTest.php` (crear)

**Interfaces:**
- Consume: `WhatsAppClient::enviarPlantilla()` de la Task 4.
- Produce: mismo formato de respuesta (`whatsapp.enviado` / `whatsapp.detalle`) documentado en `CLAUDE.md` ("Notificación WhatsApp al Crear/Editar Citas").

**Contexto verificado:** `enviarNotificacionWA` valida el teléfono de la cita (10 dígitos) *antes* de tocar `Setting` — esa validación es específica de citas (no la tiene `CarnetController`) y **no** se mueve al servicio, se queda en `AppointmentController`.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/AppointmentWhatsAppNotificationTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentWhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_envia_notificacion_con_codigo_es_co(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.cita']]], 200),
        ]);

        Setting::create([
            'wa_api_version'                => 'v18.0',
            'wa_phone_number_id'            => '1234567890',
            'wa_bearer_token'               => 'token-de-prueba',
            'wa_appointment_template_name'  => 'notificacion_cita',
        ]);

        $user   = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/appointments', [
            'afi_code'  => 123,
            'doctor_id' => $doctor->id,
            'date'      => now()->addDay()->toDateString(),
            'hour'      => '10:00',
            'address'   => 'Calle 1 # 2-3',
            'city_id'   => $doctor->city_id,
            'phone'     => '3001234567',
            'value'     => 100000,
            'type'      => 1,
            'name'      => 'Paciente Test',
            'user_id'   => $user->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('whatsapp.enviado', true);

        Http::assertSent(fn ($request) => $request['template']['language']['code'] === 'es_CO');
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que pasa ya (referencia "antes" — `AppointmentController` ya usaba `es_CO` correctamente)**

Run: `php artisan test --filter=test_store_envia_notificacion_con_codigo_es_co`
Expected: PASS incluso antes de migrar — este test fija el contrato de comportamiento para no romperlo al migrar.

- [ ] **Step 3: Migrar `enviarNotificacionWA` a usar el servicio**

En `app/Http/Controllers/AppointmentController.php`, agregar el import:
```php
use App\Services\WhatsAppClient;
```

Agregar la propiedad al constructor de la clase (si `AppointmentController` no tiene constructor propio, crear uno; si ya tiene uno, agregar el parámetro):
```php
    public function __construct(private WhatsAppClient $whatsapp)
    {
    }
```

Reemplazar el bloque desde `$recipient = '57' . $phone;` (línea 238 original) hasta el final del método `enviarNotificacionWA` (antes de su cierre) por:
```php
        $components = [
            [
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $appointment->name],
                    ['type' => 'text', 'text' => $fecha],
                    ['type' => 'text', 'text' => $appointment->hour],
                    ['type' => 'text', 'text' => $appointment->address],
                    ['type' => 'text', 'text' => $especialidad],
                    ['type' => 'text', 'text' => $nombreDoctor],
                    ['type' => 'text', 'text' => $valor],
                ],
            ],
        ];

        $resultado = $this->whatsapp->enviarPlantilla(
            $phone,
            $settings->wa_appointment_template_name,
            $components,
            'cita',
        );

        return $resultado['enviado']
            ? ['enviado' => true]
            : ['enviado' => false, 'detalle' => 'Error en la API de WhatsApp'];
    }
```

(Las validaciones de `$phone` y `$settings` al inicio del método, y el bloque que calcula `$doctor`/`$especialidad`/`$nombreDoctor`/`$fecha`/`$valor`, no cambian.)

- [ ] **Step 4: Correr el test y confirmar que sigue pasando**

Run: `php artisan test --filter=test_store_envia_notificacion_con_codigo_es_co`
Expected: PASS

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests siguen en verde.

**Checkpoint — no hacer commit.**

---

### Task 7: Migrar `WhatsAppWebhookController::sendAutoReply` a `WhatsAppClient`

**Files:**
- Modify: `app/Services/WhatsAppClient.php` (agregar método)
- Modify: `app/Http/Controllers/WhatsAppWebhookController.php`

**Interfaces:**
- Consume: la clase `WhatsAppClient` de la Task 4 (le agrega un método nuevo).
- Produce: `WhatsAppClient::enviarTexto(string $telefono, string $texto): void` — sin retorno, sin registrar en `whatsapp_messages` (mismo comportamiento actual: silencioso, no bloqueante).

**Contexto verificado:** este envío es de tipo `text` (no `template`), no registra en `whatsapp_messages`, y traga cualquier error a propósito ("Meta ya recibió el 200, no reintentar" — comentario existente). Por eso es un método aparte en el servicio, no una llamada a `enviarPlantilla()`.

- [ ] **Step 1: Escribir el test que falla**

Los tests existentes de `tests/Feature/WhatsAppWebhookTest.php` ya cubren el comportamiento de `handle()` end-to-end con `Http::fake()` (verificado: 4 tests, incluyendo `"handle retorna 200 y envía autoreply con mensaje texto"`). Ejecutarlos ahora como referencia "antes":

Run: `php artisan test --filter=WhatsAppWebhookTest`
Expected: PASS (comportamiento actual, antes de migrar).

- [ ] **Step 2: Agregar el método al servicio**

En `app/Services/WhatsAppClient.php`, agregar después de `enviarPlantilla()`:
```php
    /**
     * Envía un mensaje de texto libre (autoreply del webhook). A diferencia
     * de enviarPlantilla(), no registra en whatsapp_messages y no retorna
     * nada — los errores se ignoran a propósito (Meta ya recibió el 200 de
     * confirmación del webhook, no tiene sentido reintentar).
     */
    public function enviarTexto(string $telefono, string $texto): void
    {
        $settings = Setting::first();

        if (
            !$settings ||
            empty($settings->wa_api_version) ||
            empty($settings->wa_phone_number_id) ||
            empty($settings->wa_bearer_token)
        ) {
            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $telefono,
            'type'              => 'text',
            'text'              => ['body' => $texto],
        ];

        $apiUrl = "https://graph.facebook.com/{$settings->wa_api_version}/{$settings->wa_phone_number_id}/messages";

        try {
            $http = Http::withToken($settings->wa_bearer_token);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }
            $http->post($apiUrl, $payload);
        } catch (\Throwable) {
            // Silencioso — Meta ya recibió el 200, no reintentar.
        }
    }
```

- [ ] **Step 3: Migrar `WhatsAppWebhookController`**

En `app/Http/Controllers/WhatsAppWebhookController.php`, agregar el import:
```php
use App\Services\WhatsAppClient;
```

Agregar constructor con inyección (o extender el existente si ya tiene uno):
```php
    public function __construct(private WhatsAppClient $whatsapp)
    {
    }
```

Reemplazar el método completo `sendAutoReply` por:
```php
    private function sendAutoReply(string $phone): void
    {
        $texto = "Hola 👋 Gracias por comunicarte con Contacto Médico.\n\n"
               . "Esta línea es exclusiva para el envío de confirmaciones y documentos. "
               . "No cuenta con atención por este medio.\n\n"
               . "Para atención personalizada comunícate con tu sede más cercana 📞 "
               . "https://beacons.ai/contactomedicocolombia\n\n"
               . "¡Estamos para servirte! 🙂";

        $this->whatsapp->enviarTexto($phone, $texto);
    }
```

(Se puede eliminar `use Illuminate\Support\Facades\Http;` de este archivo si ya no se usa en ningún otro método — verificar con `grep -n "Http::" app/Http/Controllers/WhatsAppWebhookController.php` antes de borrar el import.)

- [ ] **Step 4: Correr los tests y confirmar que siguen pasando**

Run: `php artisan test --filter=WhatsAppWebhookTest`
Expected: PASS en los 4 tests existentes — el comportamiento observable (llamada HTTP, silencio en error) no cambia.

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests siguen en verde. **Este es el checkpoint que cierra la cadena Task 4→7**: los 3 controladores que duplicaban la lógica de envío WhatsApp ahora comparten un solo servicio.

**Checkpoint — no hacer commit.**

---

### Task 8: Extraer `AuthController` desde los closures de `routes/web.php`

**Files:**
- Create: `app/Http/Controllers/AuthController.php`
- Modify: `routes/web.php`
- Test: los tests existentes de `tests/Feature/AuthHintCookieTest.php` cubren `/login` y `/logout` — se reutilizan como referencia, no se crean nuevos.

**Interfaces:**
- No consume nada de otras tareas.
- Produce: `AuthController::login(Request $request)` y `AuthController::logout(Request $request)` — mismas rutas (`POST /login`, `POST /logout`), mismo comportamiento.

- [ ] **Step 1: Correr los tests existentes como referencia "antes"**

Run: `php artisan test --filter=AuthHintCookieTest`
Expected: PASS (comportamiento actual, antes de mover el código).

- [ ] **Step 2: Crear el controlador**

Crear `app/Http/Controllers/AuthController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'user'     => 'required',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('user', 'password'))) {
            return response()->json(['message' => 'Credenciales inválidas'], 422);
        }

        $request->session()->regenerate();

        // "auth_hint" NO es la fuente de verdad de autenticación (eso lo sigue
        // validando /user vía auth:sanctum) — solo le permite al middleware de
        // Next.js (proxy.ts) redirigir al login en el edge sin round-trip al
        // backend cuando claramente no hay sesión. A diferencia de XSRF-TOKEN,
        // esta cookie solo existe si hubo un login exitoso.
        return response()->json(['message' => 'Autenticado'])->cookie(
            'auth_hint',
            '1',
            config('session.lifetime'),
            config('session.path'),
            config('session.domain'),
            config('session.secure'),
            false, // httpOnly=false: no guarda nada sensible, solo es una bandera de presencia
            false,
            config('session.same_site')
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout OK'])->withCookie(
            Cookie::forget('auth_hint', config('session.path'), config('session.domain'))
        );
    }
}
```

- [ ] **Step 3: Reemplazar los closures en `routes/web.php`**

Reemplazar el archivo completo por:
```php
<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/logout', [AuthController::class, 'logout']);
```

- [ ] **Step 4: Correr los tests y confirmar que siguen pasando**

Run: `php artisan test --filter=AuthHintCookieTest`
Expected: PASS en los 3 tests — el comportamiento observable no cambia, solo dónde vive el código.

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests siguen en verde.

**Checkpoint — no hacer commit.**

---

### Task 9: Nombrar la lógica de vigencia de afiliados con scopes de Eloquent

**Files:**
- Modify: `app/Models/Affiliate.php`
- Modify: `app/Console/Commands/UpdateExpiredAffiliates.php`
- Modify: `app/Http/Controllers/AffiliateController.php:259-279` (método `expiringToday`)
- Modify: `app/Http/Controllers/DashboardController.php:23-27` (método `stats`)
- Test: `tests/Feature/AffiliateVigenciaScopesTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: `Affiliate::activosVencidos()`, `Affiliate::activosVencenHoy()`, `Affiliate::inactivosPorVencimiento()` — query scopes de Eloquent, usables como `Affiliate::activosVencidos()->get()`.

**Contexto verificado — importante:** estas 3 condiciones NO son código idéntico duplicado, son 3 combinaciones distintas de `stade`+`validity_end` dispersas en 3 archivos, fáciles de confundir entre sí:
1. `UpdateExpiredAffiliates`: `stade=1 AND validity_end < hoy` (para inactivar).
2. `AffiliateController::expiringToday()`: `stade=1 AND validity_end = hoy` (vencen hoy, alerta del dashboard).
3. `DashboardController::stats()` (`inactive_by_expiry`): `stade=2 AND validity_end < hoy` (ya inactivos por vencimiento, métrica).

Darles nombre no elimina duplicación literal (no la hay) — el valor es que un cambio futuro en la regla de vigencia (ej. un período de gracia) tiene 3 sitios con nombre explícito para revisar, en vez de 3 `where` crudos que hay que leer con cuidado para no confundir cuál es cuál.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/AffiliateVigenciaScopesTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateVigenciaScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_activos_vencidos_retorna_solo_stade_1_con_validity_end_pasado(): void
    {
        $vencido = Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->subDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->addDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 2, 'validity_end' => now()->subDay()->toDateString()]);

        $resultado = Affiliate::activosVencidos()->get();

        $this->assertCount(1, $resultado);
        $this->assertSame($vencido->id, $resultado->first()->id);
    }

    public function test_activos_vencen_hoy_retorna_solo_stade_1_con_validity_end_hoy(): void
    {
        $vencenHoy = Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->toDateString()]);
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->addDay()->toDateString()]);

        $resultado = Affiliate::activosVencenHoy()->get();

        $this->assertCount(1, $resultado);
        $this->assertSame($vencenHoy->id, $resultado->first()->id);
    }

    public function test_inactivos_por_vencimiento_retorna_solo_stade_2_con_validity_end_pasado(): void
    {
        $inactivoVencido = Affiliate::factory()->create(['stade' => 2, 'validity_end' => now()->subDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 2, 'validity_end' => now()->addDay()->toDateString()]);
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => now()->subDay()->toDateString()]);

        $resultado = Affiliate::inactivosPorVencimiento()->get();

        $this->assertCount(1, $resultado);
        $this->assertSame($inactivoVencido->id, $resultado->first()->id);
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `php artisan test --filter=AffiliateVigenciaScopesTest`
Expected: FAIL con `Call to undefined method App\Models\Affiliate::activosVencidos()`.

- [ ] **Step 3: Agregar los scopes al modelo**

En `app/Models/Affiliate.php`, agregar (usando `Illuminate\Database\Eloquent\Builder` y `Illuminate\Support\Carbon` si no están ya importados):
```php
    // Activos cuya vigencia ya pasó — candidatos a inactivar (usado por el
    // comando affiliates:update-expired).
    public function scopeActivosVencidos($query)
    {
        return $query->where('stade', 1)->where('validity_end', '<', now()->toDateString());
    }

    // Activos que vencen exactamente hoy — alerta del dashboard para que
    // los asesores gestionen la renovación antes de que se inactiven.
    public function scopeActivosVencenHoy($query)
    {
        return $query->where('stade', 1)->where('validity_end', now()->toDateString());
    }

    // Ya inactivos y además con vigencia vencida — métrica de
    // dashboard/stats (distingue inactivos "por vencimiento" de inactivos
    // por baja manual).
    public function scopeInactivosPorVencimiento($query)
    {
        return $query->where('stade', 2)->where('validity_end', '<', now()->toDateString());
    }
```

- [ ] **Step 4: Correr el test y confirmar que pasa**

Run: `php artisan test --filter=AffiliateVigenciaScopesTest`
Expected: PASS en los 3 tests.

- [ ] **Step 5: Usar los scopes en los 3 consumidores**

En `app/Console/Commands/UpdateExpiredAffiliates.php`, reemplazar:
```php
        $total = Affiliate::where('stade', 1)
            ->where('validity_end', '<', $hoy)
            ->update(['stade' => 2]);
```
por:
```php
        $total = Affiliate::activosVencidos()->update(['stade' => 2]);
```
(la variable `$hoy` puede eliminarse de este método si ya no se usa en ningún otro lugar del archivo).

En `app/Http/Controllers/AffiliateController.php`, dentro de `expiringToday()`, reemplazar:
```php
        $query = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'movil', 'phone', 'validity_end', 'stade'])
            ->with(['counselor:id,name,lastname', 'agreement:id,name'])
            ->where('stade', 1)
            ->where('validity_end', $hoy);
```
por:
```php
        $query = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'movil', 'phone', 'validity_end', 'stade'])
            ->with(['counselor:id,name,lastname', 'agreement:id,name'])
            ->activosVencenHoy();
```

En `app/Http/Controllers/DashboardController.php`, dentro de `stats()`, reemplazar:
```php
        $inactiveByExpiry = Affiliate::where('stade', 2)
                                ->where('validity_end', '<', $hoy)
                                ->count();
```
por:
```php
        $inactiveByExpiry = Affiliate::inactivosPorVencimiento()->count();
```

- [ ] **Step 6: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests siguen en verde, incluyendo `DashboardStatsTest` y `ExpiringTodayFieldsTest` (ya existentes, cubren estos dos consumidores).

**Checkpoint — no hacer commit.**

---

### Task 10: Tests Feature CRUD para `AffiliateController`

**Files:**
- Test: `tests/Feature/AffiliateControllerTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas (puede ejecutarse en paralelo con cualquier otra, pero se hace al final para probar el estado ya refactorizado del código).
- No produce interfaces nuevas — solo cobertura de tests sobre comportamiento existente.

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/AffiliateControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_afiliado_con_datos_validos(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/affiliates', [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Nuevo',
            'lastname'           => 'Afiliado',
            'id_card'            => '111222333',
            'city_id'            => $referencia->city_id,
            'validity'           => now()->toDateString(),
            'agreement_id'       => $referencia->agreement_id,
            'validity_end'       => now()->addYear()->toDateString(),
            'carnet'             => 'no',
            'state'              => 1,
            'user_id'            => $referencia->user_id,
            'payment_date'       => now()->toDateString(),
            'value'              => 100000,
            'balance'            => 0,
            'commission'         => 0,
            'payment_commission' => 'no',
            'movil'              => '3001234567',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('affiliates', ['id_card' => '111222333']);
    }

    public function test_store_crea_beneficiarios_asociados(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/affiliates', [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Con',
            'lastname'           => 'Beneficiarios',
            'id_card'            => '444555666',
            'city_id'            => $referencia->city_id,
            'validity'           => now()->toDateString(),
            'agreement_id'       => $referencia->agreement_id,
            'validity_end'       => now()->addYear()->toDateString(),
            'carnet'             => 'no',
            'state'              => 1,
            'user_id'            => $referencia->user_id,
            'payment_date'       => now()->toDateString(),
            'value'              => 100000,
            'balance'            => 0,
            'commission'         => 0,
            'payment_commission' => 'no',
            'beneficiaries'      => [['name' => 'Hijo Test', 'id_card' => '999']],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('beneficiaries', ['name' => 'Hijo Test']);
    }

    public function test_update_no_reactiva_el_afiliado_solo_por_editarlo(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 2]);

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'name' => 'Nombre editado',
        ]);

        $response->assertStatus(200);
        $this->assertSame(2, $affiliate->fresh()->stade);
    }

    public function test_destroy_elimina_el_afiliado(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/affiliates/{$affiliate->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('affiliates', ['id' => $affiliate->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->deleteJson('/api/affiliates/999999');

        $response->assertStatus(404);
    }

    public function test_index_filtra_por_stade(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['stade' => 1]);
        Affiliate::factory()->create(['stade' => 2]);

        $response = $this->actingAs($admin)->getJson('/api/affiliates?stade=2');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame(2, $data[0]['stade']);
    }
}
```

**Nota:** `test_update_no_reactiva_el_afiliado_solo_por_editarlo` verifica explícitamente la regla documentada en `CLAUDE.md` ("No se debe reactivar el afiliado solo por editarlo"). Confirmar antes de correr el test que el payload de `update()` no incluye `stade` — si `AffiliateController::update()` no toca `stade` cuando no viene en el request, el test debe pasar sin tocar código de producción; si falla, es una regresión real que hay que investigar (no forzar el test a pasar cambiando la aserción).

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=AffiliateControllerTest`
Expected: PASS en los 6 tests. Si `test_update_no_reactiva_el_afiliado_solo_por_editarlo` falla, es un hallazgo nuevo (posible violación de la regla documentada) — reportarlo, no forzar el test.

- [ ] **Step 3: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests siguen en verde.

**Checkpoint — no hacer commit.**

---

### Task 11: Tests Feature CRUD para `AppointmentController`

**Files:**
- Test: `tests/Feature/AppointmentControllerTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- No produce interfaces nuevas — solo cobertura de tests. Usa `Http::fake()` porque `store()` dispara `enviarNotificacionWA()` (migrada en la Task 6).

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/AppointmentControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin Setting configurado: enviarNotificacionWA() falla temprano
        // por configuración incompleta, sin necesidad de Http::fake() en
        // los tests que no verifican el envío de WhatsApp explícitamente.
    }

    public function test_store_crea_cita_con_datos_validos(): void
    {
        $user   = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/appointments', [
            'afi_code'  => 123,
            'doctor_id' => $doctor->id,
            'date'      => now()->addDay()->toDateString(),
            'hour'      => '10:00',
            'address'   => 'Calle 1 # 2-3',
            'city_id'   => $doctor->city_id,
            'phone'     => '3001234567',
            'value'     => 100000,
            'type'      => 1,
            'name'      => 'Paciente Test',
            'user_id'   => $user->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('appointments', ['name' => 'Paciente Test']);
    }

    public function test_store_rechaza_datos_incompletos(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/appointments', [
            'name' => 'Sin los demás campos',
        ]);

        $response->assertStatus(422);
    }

    public function test_index_normaliza_owner_segun_type(): void
    {
        $user = User::factory()->create();
        Appointment::factory()->create(['type' => 1, 'user_id' => $user->id]);
        Appointment::factory()->create(['type' => 2, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/appointments?period=all');

        $response->assertStatus(200);
        $owners = array_column($response->json('data'), 'owner');
        $this->assertContains('affiliate', $owners);
        $this->assertContains('beneficiary', $owners);
    }

    public function test_index_no_admin_solo_ve_sus_propias_citas(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Appointment::factory()->create(['user_id' => $userA->id]);
        Appointment::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->getJson('/api/appointments?period=all');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_destroy_elimina_la_cita(): void
    {
        $user        = User::factory()->create();
        $appointment = Appointment::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }
}
```

- [ ] **Step 2: Correr los tests**

Run: `php artisan test --filter=AppointmentControllerTest`
Expected: PASS en los 5 tests. `test_index_normaliza_owner_segun_type` verifica la regla documentada en `CLAUDE.md` (`owner` calculado en `index()`/`show()`, nunca persistido) — si falla, revisar el nombre real de la columna/relación antes de forzar la aserción, no asumir que el test está mal.

- [ ] **Step 3: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests siguen en verde.

**Checkpoint — no hacer commit.**

---

### Task 12: Verificación final integrada

**Files:** ninguno (solo verificación, no produce cambios de código).

**Interfaces:** consume el resultado combinado de las Tasks 1-11.

- [ ] **Step 1: Suite completa en verde**

Run: `php artisan test`
Expected: todos los tests (existentes + los ~25 nuevos de este plan) en verde, cero fallos.

- [ ] **Step 2: Confirmar manualmente el bug de la Task 1 en el flujo real**

Con el backend corriendo y la configuración real de WhatsApp (no `Http::fake`), enviar un carnet de prueba desde el panel a un número de prueba y confirmar en `whatsapp_messages` que el envío queda registrado como exitoso, sin el error `#132001`.

- [ ] **Step 3: Revisar el diff completo antes de decidir integrar**

```bash
git diff --stat
git diff
```
Expected: cambios concentrados en los archivos listados en cada tarea, sin modificaciones accidentales a `config/auth.php`, `config/cors.php`, `routes/api.php`, ni a los archivos de `auth_hint`/dashboard N+1 ya resueltos en la sesión anterior.

**Checkpoint final — el usuario decide cuándo y cómo commitear/pushear este conjunto de cambios.**
