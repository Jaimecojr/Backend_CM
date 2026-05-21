# WhatsApp Appointment Notification — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enviar automáticamente un mensaje de WhatsApp al crear o actualizar una cita médica, usando la plantilla de confirmación de cita configurada en Settings.

**Architecture:** Se agrega un método privado `enviarNotificacionWA()` en `AppointmentController` que se llama desde `store()` y `update()`. El envío es no-bloqueante: la cita se guarda siempre y el resultado de WA se incluye en la respuesta bajo la clave `whatsapp`. Se reutiliza la tabla `whatsapp_messages` agregando un campo `type` para distinguir carnets de citas.

**Tech Stack:** Laravel (PHP), WhatsApp Cloud API (Meta), Eloquent ORM, `Illuminate\Support\Facades\Http`.

---

## Mapa de archivos

| Archivo | Acción |
|---|---|
| `database/migrations/2026_05_15_000001_add_type_to_whatsapp_messages_table.php` | Crear — columna `type` string nullable |
| `database/migrations/2026_05_15_000002_add_appointment_template_to_settings_table.php` | Crear — columna `wa_appointment_template_name` string nullable |
| `app/Models/WhatsappMessage.php` | Modificar — agregar `type` a `$fillable` |
| `app/Models/Setting.php` | Modificar — agregar `wa_appointment_template_name` a `$fillable` |
| `app/Http/Controllers/CarnetController.php` | Modificar — agregar `'type' => 'carnet'` en `WhatsappMessage::create()` |
| `app/Http/Controllers/SettingController.php` | Modificar — agregar `wa_appointment_template_name` en validación de `update()` |
| `app/Http/Controllers/AppointmentController.php` | Modificar — agregar método privado + llamadas en `store()` y `update()` |

---

## Task 1: Migration — campo `type` en `whatsapp_messages`

**Files:**
- Create: `database/migrations/2026_05_15_000001_add_type_to_whatsapp_messages_table.php`

- [ ] **Step 1: Crear el archivo de migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('type')->nullable()->after('recipient_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
```

- [ ] **Step 2: Ejecutar la migration**

```bash
php artisan migrate
```

Resultado esperado: `Migrating: 2026_05_15_000001_add_type_to_whatsapp_messages_table` seguido de `Migrated`.

- [ ] **Step 3: Verificar en base de datos**

```bash
php artisan tinker
>>> \DB::select("DESCRIBE whatsapp_messages");
```

Verificar que aparece la columna `type` de tipo `varchar` nullable.

---

## Task 2: Migration — campo `wa_appointment_template_name` en `settings`

**Files:**
- Create: `database/migrations/2026_05_15_000002_add_appointment_template_to_settings_table.php`

- [ ] **Step 1: Crear el archivo de migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('wa_appointment_template_name')->nullable()->after('wa_template_name');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('wa_appointment_template_name');
        });
    }
};
```

- [ ] **Step 2: Ejecutar la migration**

```bash
php artisan migrate
```

Resultado esperado: `Migrating: 2026_05_15_000002_add_appointment_template_to_settings_table` seguido de `Migrated`.

- [ ] **Step 3: Verificar**

```bash
php artisan tinker
>>> \DB::select("DESCRIBE settings");
```

Verificar que aparece `wa_appointment_template_name` nullable.

---

## Task 3: Actualizar modelos

**Files:**
- Modify: `app/Models/WhatsappMessage.php`
- Modify: `app/Models/Setting.php`

- [ ] **Step 1: Agregar `type` al fillable de `WhatsappMessage`**

Reemplazar el contenido de `app/Models/WhatsappMessage.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'response',
        'recipient_id',
        'deleted',
        'type',
    ];
}
```

- [ ] **Step 2: Agregar `wa_appointment_template_name` al fillable de `Setting`**

Reemplazar el contenido de `app/Models/Setting.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'wa_api_version',
        'wa_phone_number_id',
        'wa_bearer_token',
        'wa_template_name',
        'wa_appointment_template_name',
    ];
}
```

- [ ] **Step 3: Verificar con tinker**

```bash
php artisan tinker
>>> App\Models\WhatsappMessage::make(['type' => 'cita'])->type;
// Debe retornar: "cita"
>>> App\Models\Setting::first()->wa_appointment_template_name;
// Debe retornar: null (aún no configurado)
```

---

## Task 4: Actualizar `CarnetController` — registrar `type = 'carnet'`

**Files:**
- Modify: `app/Http/Controllers/CarnetController.php:100-104`

- [ ] **Step 1: Agregar `type` al `WhatsappMessage::create()` en el carnet**

Localizar en `CarnetController.php` el bloque:

```php
WhatsappMessage::create([
    'response'     => json_encode($responseData),
    'recipient_id' => $recipient,
    'deleted'      => 0,
]);
```

Reemplazarlo por:

```php
WhatsappMessage::create([
    'response'     => json_encode($responseData),
    'recipient_id' => $recipient,
    'deleted'      => 0,
    'type'         => 'carnet',
]);
```

- [ ] **Step 2: Verificar manualmente**

Enviar un carnet desde el panel (o via Postman: `POST /api/affiliates/{id}/carnet`) y luego:

```bash
php artisan tinker
>>> App\Models\WhatsappMessage::latest()->first()->type;
// Debe retornar: "carnet"
```

---

## Task 5: Actualizar `SettingController` — validar nuevo campo

**Files:**
- Modify: `app/Http/Controllers/SettingController.php:27-31`

- [ ] **Step 1: Agregar `wa_appointment_template_name` a las reglas de validación**

Localizar en `SettingController.php` el array de reglas dentro de `update()`:

```php
$validator = Validator::make($request->all(), [
    'wa_api_version'     => 'required|string|max:255',
    'wa_phone_number_id' => 'required|string|max:255',
    'wa_bearer_token'    => 'required|string|max:255',
    'wa_template_name'   => 'required|string|max:255',
]);
```

Reemplazarlo por:

```php
$validator = Validator::make($request->all(), [
    'wa_api_version'               => 'required|string|max:255',
    'wa_phone_number_id'           => 'required|string|max:255',
    'wa_bearer_token'              => 'required|string|max:255',
    'wa_template_name'             => 'required|string|max:255',
    'wa_appointment_template_name' => 'nullable|string|max:255',
]);
```

- [ ] **Step 2: Verificar via Postman o curl**

```bash
curl -X PUT http://localhost:8000/api/settings/1 \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "wa_api_version": "v19.0",
    "wa_phone_number_id": "123456",
    "wa_bearer_token": "abc",
    "wa_template_name": "carnet_template",
    "wa_appointment_template_name": "confirmacion_cita"
  }'
```

Resultado esperado: `200 OK` con `"message": "Configuración actualizada exitosamente."` y el campo `wa_appointment_template_name` en `data`.

---

## Task 6: Agregar `enviarNotificacionWA()` en `AppointmentController`

**Files:**
- Modify: `app/Http/Controllers/AppointmentController.php`

- [ ] **Step 1: Agregar los imports necesarios al tope del controlador**

Localizar el bloque de `use` al inicio de `AppointmentController.php`:

```php
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
```

Reemplazarlo por:

```php
use App\Models\Appointment;
use App\Models\Setting;
use App\Models\WhatsappMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
```

- [ ] **Step 2: Agregar el método privado al final de la clase (antes del cierre `}`)**

```php
private function enviarNotificacionWA(Appointment $appointment): array
{
    // Validar teléfono
    $phone = preg_replace('/\D/', '', (string) $appointment->phone);
    if (strlen($phone) !== 10) {
        return ['enviado' => false, 'detalle' => 'El teléfono de la cita no es válido'];
    }

    // Validar configuración de WhatsApp
    $settings = Setting::first();
    if (
        !$settings ||
        empty($settings->wa_api_version) ||
        empty($settings->wa_phone_number_id) ||
        empty($settings->wa_bearer_token) ||
        empty($settings->wa_appointment_template_name)
    ) {
        return ['enviado' => false, 'detalle' => 'Configuración de WhatsApp incompleta'];
    }

    // Cargar relaciones para la plantilla
    $appointment->loadMissing('doctor.specialty');

    $doctor         = $appointment->doctor;
    $especialidad   = $doctor?->specialty?->name ?? 'No especificada';
    $nombreDoctor   = $doctor ? trim($doctor->name . ' ' . $doctor->lastname) : 'No asignado';
    $fecha          = \Carbon\Carbon::parse($appointment->date)->format('d/m/Y');
    $valor          = '$ ' . number_format($appointment->value, 0, ',', '.');

    $recipient = '57' . $phone;

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $recipient,
        'type'              => 'template',
        'template'          => [
            'name'       => $settings->wa_appointment_template_name,
            'language'   => ['code' => 'es'],
            'components' => [
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
            ],
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
        'type'         => 'cita',
    ]);

    if (!empty($responseData['messages'][0]['id'])) {
        return ['enviado' => true];
    }

    return ['enviado' => false, 'detalle' => 'Error en la API de WhatsApp'];
}
```

---

## Task 7: Integrar `enviarNotificacionWA()` en `store()` y `update()`

**Files:**
- Modify: `app/Http/Controllers/AppointmentController.php:100-107` y `:154-161`

- [ ] **Step 1: Actualizar `store()` para llamar al método y retornar el resultado**

Localizar en `store()` el bloque final:

```php
$appointment = Appointment::create($validator->validated());

return response()->json([
    'message' => 'Cita creada correctamente.',
    'data'    => $appointment,
], 201);
```

Reemplazarlo por:

```php
$appointment = Appointment::create($validator->validated());

$whatsapp = $this->enviarNotificacionWA($appointment);

return response()->json([
    'message'   => 'Cita creada correctamente.',
    'data'      => $appointment,
    'whatsapp'  => $whatsapp,
], 201);
```

- [ ] **Step 2: Actualizar `update()` para llamar al método y retornar el resultado**

Localizar en `update()` el bloque final:

```php
$appointment->update($validator->validated());

return response()->json([
    'message' => 'Cita actualizada correctamente.',
    'data'    => $appointment,
]);
```

Reemplazarlo por:

```php
$appointment->update($validator->validated());

$whatsapp = $this->enviarNotificacionWA($appointment);

return response()->json([
    'message'  => 'Cita actualizada correctamente.',
    'data'     => $appointment,
    'whatsapp' => $whatsapp,
]);
```

---

## Task 8: Verificación final

- [ ] **Step 1: Crear una cita via Postman y verificar respuesta**

```bash
curl -X POST http://localhost:8000/api/appointments \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "afi_code": 1,
    "doctor_id": 1,
    "date": "2026-05-20",
    "hour": "10:00 AM",
    "address": "Calle 45 # 23-10",
    "city_id": 1,
    "phone": "3001234567",
    "value": 50000,
    "type": 1,
    "name": "Juan Pérez",
    "user_id": 1
  }'
```

Resultado esperado con WA configurado y plantilla aprobada:
```json
{
  "message": "Cita creada correctamente.",
  "data": { ... },
  "whatsapp": { "enviado": true }
}
```

Resultado esperado sin `wa_appointment_template_name` configurado:
```json
{
  "message": "Cita creada correctamente.",
  "data": { ... },
  "whatsapp": { "enviado": false, "detalle": "Configuración de WhatsApp incompleta" }
}
```

- [ ] **Step 2: Verificar registro en `whatsapp_messages`**

```bash
php artisan tinker
>>> App\Models\WhatsappMessage::latest()->first();
// type debe ser "cita"
```

- [ ] **Step 3: Actualizar una cita y verificar que también dispara WA**

```bash
curl -X PUT http://localhost:8000/api/appointments/1 \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "afi_code": 1,
    "doctor_id": 1,
    "date": "2026-05-21",
    "hour": "11:00 AM",
    "address": "Calle 45 # 23-10",
    "city_id": 1,
    "phone": "3001234567",
    "value": 50000,
    "type": 1,
    "name": "Juan Pérez",
    "user_id": 1
  }'
```

Resultado esperado: `200 OK` con clave `whatsapp` en la respuesta.

- [ ] **Step 4: Probar con teléfono inválido**

Enviar la misma petición con `"phone": "123"` — la cita debe crearse igualmente y la respuesta debe incluir:
```json
"whatsapp": { "enviado": false, "detalle": "El teléfono de la cita no es válido" }
```
