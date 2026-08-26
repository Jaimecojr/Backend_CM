# Retrofit dev-standards Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar las brechas de `dev-standards` que quedan en el backend tras la ronda de arquitectura
del 2026-08-07: corregir un bug de autorización activo, agregar cobertura de tests a los 11
controladores vivos que no tienen ninguno, activar `declare(strict_types=1)` en todo `app/`,
extraer la sincronización de beneficiarios a un Servicio, introducir Form Requests donde hay
validación duplicada, y completar la documentación PHPDoc en los puntos identificados.

**Architecture:** Las tareas 1-11 (bug fix + config + tests) son independientes entre sí y pueden
ejecutarse en cualquier orden, pero deben ir **antes** de la Tarea 12 (`strict_types`) — sin
cobertura, activar tipado estricto puede esconder un `TypeError` nuevo hasta producción en vez de
detectarlo en CI. Las tareas 13-15 (extracción de Servicio, Form Requests) dependen de que sus
controladores ya tengan test (Affiliate y Appointment ya lo tienen desde el 2026-08-07; User se
cubre en la Tarea 5 de este plan). La Tarea 16 (PHPDoc) y 17 (verificación final) van al final.

**Tech Stack:** Laravel 11, PHPUnit (`php artisan test`), SQLite en memoria en tests
(`phpunit.xml`), `RefreshDatabase` + `actingAs()` para auth de sesión en tests (sin necesidad de
tokens Sanctum explícitos).

**Spec:** `docs/superpowers/specs/2026-08-25-retrofit-dev-standards-backend-design.md`

## Global Constraints

- Comentarios, nombres de variables y mensajes de respuesta JSON en **español** — regla del
  proyecto (`CLAUDE.md`), excepción documentada frente al estándar genérico de `dev-standards`
  (ver Hallazgo 3.2 del spec). No traducir nada existente.
- **No hacer `git commit` dentro de las tareas.** Cada tarea termina en un checkpoint de
  verificación (tests en verde). El usuario decide cuándo integrar (`[[feedback_no_commits_until_ready]]`).
- **En Windows, correr los tests con `XDEBUG_MODE=off php artisan test`** — con Xdebug activo,
  `WhatsAppClientTest::test_enviar_plantilla_maneja_error_de_red` produce un segfault (confirmado
  en esta sesión). No es un bug del código, es el entorno.
- Los controladores de este proyecto devuelven **400** (no 422) en errores de validación —
  `Validator::make()` con manejo manual, no `$request->validate()` con excepción automática. Todo
  test nuevo debe esperar 400, salvo en los 2 casos donde el controlador usa `$request->validate()`
  directo (`AffiliateNoteController::store()`, que sigue el default de Laravel) — confirmar el
  código real antes de fijar la aserción si hay duda.
- No se testean ni se tocan los 9 controladores del "Grupo B" del Hallazgo 2.2 del spec
  (`CarouselController`, `FormController`, `FormCounselorController`, `ModuleController`,
  `RegistActionController`, `UserPropertyController`, `WhatsappMessageController`,
  `MembershipFormBeneficiaryController`, `AdminController`) — son código muerto o huérfano sin
  rutas registradas; su disposición (implementar vs eliminar) es una decisión de producto
  pendiente, fuera de alcance de este plan.
- No se reabre nada del spec/plan `2026-08-07-revision-arquitectura-backend` (ya verificado en esta
  sesión, con un único hallazgo real que sí está en este plan: Tarea 1).

---

### Tarea 1: Bug activo — `AffiliateController::update()` no restringe `stade` a super admin

**Files:**
- Modify: `app/Http/Controllers/AffiliateController.php` (método `update()`)
- Test: `tests/Feature/AffiliateStadeAuthorizationTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- No produce interfaz nueva — es una guarda de autorización sobre un endpoint existente.

**Contexto verificado:** `update()` valida `'stade' => 'nullable|integer'` y persiste
`$affiliate->update($request->only([..., 'stade', ...]))` sin verificar
`$request->user()->esSuperAdmin()`. El resto de los checks de super admin en el proyecto
(`AgreementController`, `DashboardController`) responden 403 — se sigue el mismo patrón aquí.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/AffiliateStadeAuthorizationTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateStadeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_admin_no_puede_cambiar_stade(): void
    {
        $asesor = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($asesor)->patchJson("/api/affiliates/{$affiliate->id}", [
            'stade' => 2,
        ]);

        $response->assertStatus(403);
        $this->assertSame(1, $affiliate->fresh()->stade);
    }

    public function test_super_admin_si_puede_cambiar_stade(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'stade' => 2,
        ]);

        $response->assertStatus(200);
        $this->assertSame(2, $affiliate->fresh()->stade);
    }

    public function test_no_admin_puede_editar_otros_campos_sin_enviar_stade(): void
    {
        $asesor = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($asesor)->patchJson("/api/affiliates/{$affiliate->id}", [
            'name' => 'Nombre editado',
        ]);

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `XDEBUG_MODE=off php artisan test --filter=AffiliateStadeAuthorizationTest`
Expected: FAIL en `test_no_admin_no_puede_cambiar_stade` (hoy responde 200 y sí cambia `stade`).

- [ ] **Step 3: Aplicar el fix**

En `app/Http/Controllers/AffiliateController.php`, al inicio de `update()` (antes de construir el
array de campos a persistir), agregar:
```php
        if ($request->has('stade') && !$request->user()->esSuperAdmin()) {
            return response()->json([
                'message' => 'No tiene permisos para cambiar el estado del afiliado.',
            ], 403);
        }
```

- [ ] **Step 4: Correr el test y confirmar que pasa**

Run: `XDEBUG_MODE=off php artisan test --filter=AffiliateStadeAuthorizationTest`
Expected: PASS en los 3 tests.

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todos los tests existentes siguen en verde (incluyendo
`test_update_no_reactiva_el_afiliado_solo_por_editarlo`, que no envía `stade` y no debe verse
afectado).

**Checkpoint — no hacer commit.**

---

### Tarea 2: `RenovationController` — restringir rutas a las que sí están implementadas

**Files:**
- Modify: `routes/api.php`
- Test: `tests/Feature/RenovationRoutesTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- No produce interfaz nueva.

**Contexto verificado:** `Route::apiResource('renovations', RenovationController::class)` registra
`PUT/PATCH` y `DELETE`, pero el controlador solo implementa `index()`, `store()`, `show()`. Llamar
esos verbos hoy produce un error fatal de PHP en vez de un 404/405 limpio.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/RenovationRoutesTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Renovation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenovationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_sobre_renovation_no_registrada_retorna_404(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->putJson('/api/renovations/1', ['value' => 1000]);

        $response->assertStatus(404);
    }

    public function test_delete_sobre_renovation_no_registrada_retorna_404(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->deleteJson('/api/renovations/1');

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `XDEBUG_MODE=off php artisan test --filter=RenovationRoutesTest`
Expected: ERROR (fatal `Call to undefined method RenovationController::update()`), no un simple FAIL.

- [ ] **Step 3: Aplicar el fix**

En `routes/api.php`, buscar la línea con `Route::apiResource('renovations', ...)` y cambiarla por:
```php
Route::apiResource('renovations', RenovationController::class)->only(['index', 'store', 'show']);
```

- [ ] **Step 4: Correr el test y confirmar que pasa**

Run: `XDEBUG_MODE=off php artisan test --filter=RenovationRoutesTest`
Expected: PASS en los 2 tests (ahora Laravel responde 404 porque la ruta no existe, en vez de un
fatal error).

- [ ] **Step 5: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 3: Configurar cobertura de tests + documentar convención en `CLAUDE.md`

**Files:**
- Modify: `phpunit.xml`
- Modify: `composer.json`
- Modify: `CLAUDE.md`

**Interfaces:** ninguna — es configuración y documentación, no código de producción.

- [ ] **Step 1: Agregar bloque de cobertura a `phpunit.xml`**

Dentro de la etiqueta raíz `<phpunit>`, agregar (si no existe ya un bloque `<source>`):
```xml
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
```

- [ ] **Step 2: Agregar script de cobertura a `composer.json`**

En la sección `"scripts"`, agregar junto al script `"test"` existente:
```json
        "test:coverage": [
            "@php artisan config:clear --ansi",
            "@php artisan test --coverage --min=0"
        ]
```
(El `--min=0` es intencional por ahora — se sube a 85 una vez que las Tareas 4-11 den cobertura
real a los controladores nuevos; subirlo antes bloquearía el checkpoint de cada tarea sin razón.)

- [ ] **Step 3: Verificar que corre**

Run: `XDEBUG_MODE=off composer test:coverage`
Expected: corre sin error de configuración (requiere PCOV o Xdebug con `coverage` habilitado en el
`php.ini` local — si falla con "no code coverage driver available", instalar PCOV:
`composer require --dev pcov/clobber` no aplica en Windows; en su lugar habilitar la extensión
`pcov` o `xdebug.mode=coverage` en el `php.ini` usado por la CLI, y confirmar con `php -m | grep -i "pcov\|xdebug"`).

- [ ] **Step 4: Documentar la convención de tests en `CLAUDE.md`**

Agregar una sección nueva antes de "## Reglas Generales":
```markdown
## Testing

- **Convención de ubicación:** `tests/Unit/` (lógica pura, sin framework) y `tests/Feature/`
  (HTTP, DB, integración) — la convención por defecto de Laravel, que ya es "carpeta espejo" según
  el estándar de `dev-standards`. No mezclar con colocación (`*.test.php` junto al código).
- **Cobertura:** `composer test:coverage` genera el reporte. Objetivo 85%+ de líneas/branches en
  `app/`, sin bloquear commits mientras la cobertura de los módulos nuevos sube gradualmente.
- **En Windows:** correr `XDEBUG_MODE=off php artisan test` — con Xdebug activo, un test que fuerza
  una excepción de red dentro de `Http::fake()` produce un segfault.
```

- [ ] **Step 5: Confirmar que la suite sigue en verde**

Run: `XDEBUG_MODE=off php artisan test`
Expected: sin cambios de comportamiento, solo configuración/documentación.

**Checkpoint — no hacer commit.**

---

### Tarea 4: Tests Feature — `RenovationController`

**Files:**
- Test: `tests/Feature/RenovationControllerTest.php` (crear)

**Interfaces:**
- Depende de la Tarea 2 (rutas ya restringidas) para que los tests de `index/store/show` no
  colisionen con las rutas eliminadas.
- No produce interfaz nueva.

**Contexto verificado:** columnas NOT NULL de `renovations`: `date_ini`, `date_end`,
`date_payment`, `value`, `affiliate_id`. Validación real de `store()`:
```php
'affiliate_id' => 'required|exists:affiliates,id',
'date_ini'     => 'required|date',
'date_end'     => 'required|date|after_or_equal:date_ini',
'date_payment' => 'required|date',
'value'        => 'required|numeric',
```
Tras crear la renovación, el controlador reactiva el afiliado solo si `stade == 2`:
`Affiliate::where('id', $request->affiliate_id)->where('stade', 2)->update(['stade' => 1]);`.
No existe `RenovationFactory` — se crea con `Renovation::create()` directo cuando se necesite un
registro previo.

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/RenovationControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Renovation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenovationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_renovacion_con_datos_validos(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('renovations', ['affiliate_id' => $affiliate->id, 'value' => 150000]);
    }

    public function test_store_rechaza_date_end_anterior_a_date_ini(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->subDay()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['date_end']);
    }

    public function test_store_reactiva_el_afiliado_si_estaba_inactivo(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create(['stade' => 2]);

        $response = $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, $affiliate->fresh()->stade);
    }

    public function test_store_no_toca_stade_si_el_afiliado_ya_estaba_activo(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $this->assertSame(1, $affiliate->fresh()->stade);
    }

    public function test_index_lista_renovaciones(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create();
        Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/renovations');

        $response->assertStatus(200);
    }

    public function test_show_retorna_la_renovacion(): void
    {
        $admin = User::factory()->create();
        $affiliate = Affiliate::factory()->create();
        $renovation = Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini' => now()->toDateString(),
            'date_end' => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value' => 150000,
        ]);

        $response = $this->actingAs($admin)->getJson("/api/renovations/{$renovation->id}");

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=RenovationControllerTest`
Expected: PASS en los 6 tests. Si `test_store_crea_renovacion_con_datos_validos` falla el status
(201 vs 200), leer el `return response()->json(...)` real de `RenovationController::store()` y
ajustar la aserción al código real, no forzar el test.

- [ ] **Step 3: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 5: Tests Feature — `UserController`

**Files:**
- Test: `tests/Feature/UserControllerCrudTest.php` (crear — nombre distinto de
  `UserMovilValidationTest`/`UserSuperAdminTest` ya existentes, para no colisionar)

**Interfaces:** no consume ni produce nada de otras tareas.

**Contexto verificado:** `UserFactory` por defecto crea `type=2` (franquicia) con `city_id` propio
(crea department+city). Campos NOT NULL sin default a incluir en creación manual vía HTTP: `nit`,
`name`, `email`, `user`, `password`, `city_id`. Validación `store()`:
```php
'nit' => 'required|regex:/^\d+$/|max:100|unique:users,nit',
'name' => 'required|string|max:100',
'email' => 'required|email|unique:users,email',
'user' => 'required|string|max:100|unique:users,user',
'password' => 'required|string|min:6',
'city_id' => 'required|exists:cities,id',
'movil' => 'nullable|digits:10',
'type' => 'nullable|in:1,2,3',
```
Si no se envía `type`, el controlador lo fuerza a `2`.

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/UserControllerCrudTest.php`:
```php
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
```

- [ ] **Step 2: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=UserControllerCrudTest`
Expected: PASS en los 7 tests.

- [ ] **Step 3: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde, incluyendo `UserMovilValidationTest`/`UserSuperAdminTest` existentes.

**Checkpoint — no hacer commit.**

---

### Tarea 6: Tests Feature — `DoctorController`

**Files:**
- Test: `tests/Feature/DoctorControllerTest.php` (crear)

**Interfaces:** no consume ni produce nada de otras tareas.

**Contexto verificado:** `DoctorFactory` genera `specialty_id`/`city_id`/`name`/`lastname`/`phone`/
`movil`/`address`/`secretary_name`/`value_agreement`/`state=1` automáticamente. Validación
`store()`:
```php
'name' => 'required|string|max:255', 'lastname' => 'required|string|max:255',
'specialty_id' => 'required|exists:specialties,id', 'city_id' => 'required|exists:cities,id',
'phone' => 'required|string|max:255', 'movil' => 'required|digits:10',
'address' => 'required|string|max:255', 'secretary_name' => 'required|string|max:255',
'value_agreement' => 'required|numeric|min:10000', 'state' => 'nullable|in:1,2',
```

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/DoctorControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(Doctor $referencia): array
    {
        return [
            'name' => 'Carlos',
            'lastname' => 'Ramírez',
            'specialty_id' => $referencia->specialty_id,
            'city_id' => $referencia->city_id,
            'phone' => '6041234567',
            'movil' => '3009876543',
            'address' => 'Calle 10 # 5-20',
            'secretary_name' => 'Secretaria Test',
            'value_agreement' => 80000,
            'state' => 1,
        ];
    }

    public function test_store_crea_medico_con_datos_validos(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/doctors', $this->payloadValido($referencia));

        $response->assertStatus(201);
        $this->assertDatabaseHas('doctors', ['name' => 'Carlos', 'lastname' => 'Ramírez']);
    }

    public function test_store_rechaza_movil_invalido(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();
        $payload = $this->payloadValido($referencia);
        $payload['movil'] = '123';

        $response = $this->actingAs($admin)->postJson('/api/doctors', $payload);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_store_rechaza_value_agreement_menor_al_minimo(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();
        $payload = $this->payloadValido($referencia);
        $payload['value_agreement'] = 5000;

        $response = $this->actingAs($admin)->postJson('/api/doctors', $payload);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['value_agreement']);
    }

    public function test_update_permite_edicion_parcial(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'value_agreement' => 120000,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'value_agreement' => 120000]);
    }

    public function test_destroy_elimina_el_medico(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/doctors/{$doctor->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('doctors', ['id' => $doctor->id]);
    }

    public function test_index_lista_medicos(): void
    {
        $admin = User::factory()->create();
        Doctor::factory()->count(2)->create();

        $response = $this->actingAs($admin)->getJson('/api/doctors');

        $response->assertStatus(200);
    }
}
```

**Nota para el ejecutor:** antes de escribir un test para `GET /api/doctors/by-specialty`, leer
`DoctorController::bySpecialty()` para confirmar el nombre exacto del query param (no se verificó
en la investigación previa) — si el tiempo no lo permite, omitir ese caso y dejarlo anotado como
pendiente en el PR, no adivinar el nombre del parámetro.

- [ ] **Step 2: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=DoctorControllerTest`
Expected: PASS en los 6 tests.

- [ ] **Step 3: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 7: Tests Feature — `CounselorController`

**Files:**
- Test: `tests/Feature/CounselorControllerTest.php` (crear)

**Interfaces:** no consume ni produce nada de otras tareas.

**Contexto verificado:** no existe `CounselorFactory`. Campos NOT NULL sin default: `name`,
`lastname`, `id_card`, `type_contra`, `rol`, `city_id`, `user_id`. Validación `store()`:
```php
'name' => 'required|string|max:255', 'lastname' => 'required|string|max:255',
'id_card' => 'required|regex:/^\d+$/|max:100|unique:counselors,id_card',
'type_contra' => 'required|in:Término Fijo,Término Indefinido,Corretaje,Con Garantizado',
'rol' => 'required|numeric', 'movil' => 'nullable|digits:10',
'city_id' => 'required|exists:cities,id', 'user_id' => 'required|exists:users,id',
```

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/CounselorControllerTest.php`:
```php
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
}
```

**Nota para el ejecutor:** confirmar el nombre real del query param de `checkIdCard()` (asumido
`id_card` por convención del resto del proyecto) leyendo el método antes de fijar la aserción si el
test falla por 422/400 inesperado.

- [ ] **Step 2: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=CounselorControllerTest`
Expected: PASS en los 5 tests (ajustando el query param de `check-id-card` si difiere).

- [ ] **Step 3: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 8: Tests Feature — `AgreementController` + `AffiliateNoteController`

**Files:**
- Test: `tests/Feature/AgreementControllerTest.php` (crear)
- Test: `tests/Feature/AffiliateNoteControllerTest.php` (crear)

**Interfaces:** no consume ni produce nada de otras tareas.

**Contexto verificado:** `AgreementController::store()`/`update()` exigen
`$request->user()->esSuperAdmin()`, `destroy()` no. Validación (idéntica en store/update, **todo
requerido incluso en update**):
```php
'name' => 'required|string|max:255', 'amount' => 'required|integer|min:10000',
'state' => 'required|in:1,0', 'city_id' => 'required|exists:cities,id',
```
`AffiliateNoteController::destroy()` exige `esSuperAdmin()`, `index`/`store` no. Validación
`store()` (vía `$request->validate()`, no `Validator::make()` — puede devolver 422 default de
Laravel, no 400; confirmar antes de fijar la aserción): `'body' => 'required|string|max:2000'`.
`user_id` se asigna automáticamente desde `$request->user()->id`.

- [ ] **Step 1: Escribir los tests de `AgreementController`**

Crear `tests/Feature/AgreementControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payloadValido(): array
    {
        return [
            'name' => 'Convenio Test',
            'amount' => 150000,
            'state' => 1,
            'city_id' => User::factory()->create()->city_id,
        ];
    }

    public function test_store_requiere_super_admin(): void
    {
        $asesor = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($asesor)->postJson('/api/agreements', $this->payloadValido());

        $response->assertStatus(403);
    }

    public function test_store_crea_convenio_siendo_super_admin(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());

        $response->assertStatus(201);
        $this->assertDatabaseHas('agreements', ['name' => 'Convenio Test']);
    }

    public function test_update_requiere_super_admin(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $asesor = User::factory()->create(['type' => 3]);
        $response = $this->actingAs($asesor)->putJson("/api/agreements/{$id}", $this->payloadValido());

        $response->assertStatus(403);
    }

    public function test_destroy_no_requiere_super_admin(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $asesor = User::factory()->create(['type' => 3]);
        $response = $this->actingAs($asesor)->deleteJson("/api/agreements/{$id}");

        $response->assertStatus(200);
    }

    public function test_active_agreements_retorna_solo_activos(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $inactivo = $this->payloadValido();
        $inactivo['state'] = 0;
        $this->actingAs($admin)->postJson('/api/agreements', $inactivo);

        $response = $this->actingAs($admin)->getJson('/api/agreements/active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
```

- [ ] **Step 2: Escribir los tests de `AffiliateNoteController`**

Crear `tests/Feature/AffiliateNoteControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_nota_para_el_afiliado(): void
    {
        $user = User::factory()->create();
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", [
            'body' => 'Nota de seguimiento de prueba.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('affiliate_notes', [
            'affiliate_id' => $affiliate->id,
            'user_id' => $user->id,
            'body' => 'Nota de seguimiento de prueba.',
        ]);
    }

    public function test_store_rechaza_body_vacio(): void
    {
        $user = User::factory()->create();
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", [
            'body' => '',
        ]);

        $response->assertStatus(422); // $request->validate() usa el default de Laravel, no Validator::make()
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_index_lista_notas_del_afiliado(): void
    {
        $user = User::factory()->create();
        $affiliate = Affiliate::factory()->create();
        $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", ['body' => 'Primera nota']);

        $response = $this->actingAs($user)->getJson("/api/affiliates/{$affiliate->id}/notes");

        $response->assertStatus(200);
    }

    public function test_destroy_requiere_super_admin(): void
    {
        $user = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create();
        $created = $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", ['body' => 'Nota']);
        $noteId = $created->json('data.id');

        $response = $this->actingAs($user)->deleteJson("/api/affiliates/{$affiliate->id}/notes/{$noteId}");

        $response->assertStatus(403);
    }

    public function test_destroy_retorna_404_si_la_nota_no_pertenece_al_afiliado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliateA = Affiliate::factory()->create();
        $affiliateB = Affiliate::factory()->create();
        $created = $this->actingAs($admin)->postJson("/api/affiliates/{$affiliateA->id}/notes", ['body' => 'Nota']);
        $noteId = $created->json('data.id');

        $response = $this->actingAs($admin)->deleteJson("/api/affiliates/{$affiliateB->id}/notes/{$noteId}");

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 3: Correr los tests, ajustando códigos de estado al comportamiento real**

Run: `XDEBUG_MODE=off php artisan test --filter=AgreementControllerTest`
Run: `XDEBUG_MODE=off php artisan test --filter=AffiliateNoteControllerTest`
Expected: PASS. Si algún código de estado (201 vs 200, 422 vs 400) no coincide, leer el método real
del controlador y corregir la aserción del test al comportamiento verificado — no cambiar el
controlador para que coincida con un valor supuesto.

- [ ] **Step 4: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 9: Tests Feature — `MembershipFormController` (admin) + `SettingController`

**Files:**
- Test: `tests/Feature/MembershipFormAdminControllerTest.php` (crear — nombre distinto de un
  posible `MembershipFormAdminTest` previo si existiera)
- Test: `tests/Feature/SettingControllerTest.php` (crear)

**Interfaces:** no consume ni produce nada de otras tareas.

**Contexto verificado:** rutas admin de `MembershipForm`: `index/show/destroy` +
`PATCH .../convert` (`markConverted`). No hay `store`/`update` admin (store es la ruta pública).
`SettingController` no tiene `store` (singleton, se asume una fila ya creada); `update()` usa Route
Model Binding y exige todos los campos de WhatsApp:
```php
'wa_api_version' => 'required|string|max:255', 'wa_phone_number_id' => 'required|string|max:255',
'wa_bearer_token' => 'required|string', 'wa_template_name' => 'required|string|max:255',
'wa_appointment_template_name' => 'nullable|string|max:255',
```
`index()` devuelve 404 si no hay ninguna fila (`Setting::first()` es null).

- [ ] **Step 1: Escribir los tests de `MembershipFormController`**

Antes de escribir, correr `grep -rl "membership-forms\|MembershipForm" tests/Feature` para
confirmar que no existe ya un archivo que cubra estas rutas (evitar duplicar). Si no existe:

Crear `tests/Feature/MembershipFormAdminControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\MembershipForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFormAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lista_solo_solicitudes_pendientes(): void
    {
        $admin = User::factory()->create();
        MembershipForm::factory()->count(2)->create(['state' => 0]);
        MembershipForm::factory()->create(['state' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/membership-forms');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_show_retorna_la_solicitud(): void
    {
        $admin = User::factory()->create();
        $form = MembershipForm::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/membership-forms/{$form->id}");

        $response->assertStatus(200);
    }

    public function test_destroy_elimina_la_solicitud(): void
    {
        $admin = User::factory()->create();
        $form = MembershipForm::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/membership-forms/{$form->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('membership_forms', ['id' => $form->id]);
    }

    public function test_convert_marca_state_1_y_lo_saca_del_listado(): void
    {
        $admin = User::factory()->create();
        $form = MembershipForm::factory()->create(['state' => 0]);

        $response = $this->actingAs($admin)->patchJson("/api/membership-forms/{$form->id}/convert");

        $response->assertStatus(200);
        $this->assertDatabaseHas('membership_forms', ['id' => $form->id, 'state' => 1]);

        $index = $this->actingAs($admin)->getJson('/api/membership-forms');
        $this->assertCount(0, $index->json('data'));
    }
}
```

- [ ] **Step 2: Escribir los tests de `SettingController`**

Crear `tests/Feature/SettingControllerTest.php`:
```php
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
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->getJson('/api/settings');

        $response->assertStatus(404);
    }

    public function test_index_retorna_la_configuracion_existente(): void
    {
        $admin = User::factory()->create();
        $this->crearSetting();

        $response = $this->actingAs($admin)->getJson('/api/settings');

        $response->assertStatus(200);
    }

    public function test_update_actualiza_la_configuracion(): void
    {
        $admin = User::factory()->create();
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
        $admin = User::factory()->create();
        $setting = $this->crearSetting();

        $response = $this->actingAs($admin)->patchJson("/api/settings/{$setting->id}", [
            'wa_api_version' => 'v19.0',
            'wa_phone_number_id' => '1234567890',
            'wa_bearer_token' => '',
            'wa_template_name' => 'plantilla_inicial',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['wa_bearer_token']);
    }
}
```

- [ ] **Step 3: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=MembershipFormAdminControllerTest`
Run: `XDEBUG_MODE=off php artisan test --filter=SettingControllerTest`
Expected: PASS en ambos archivos.

- [ ] **Step 4: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 10: Tests Feature — catálogos `SpecialtyController`, `CityController`, `DepartmentController`

**Files:**
- Test: `tests/Feature/SpecialtyControllerTest.php` (crear)
- Test: `tests/Feature/CityControllerTest.php` (crear)
- Test: `tests/Feature/DepartmentControllerTest.php` (crear)

**Interfaces:** no consume ni produce nada de otras tareas.

**Contexto verificado:** ninguno de los 3 modelos tiene factory. `Specialty` tiene CRUD completo;
`City` solo `getByDepartment(Department $department)`; `Department` solo `index()`.

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/SpecialtyControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_especialidad(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => 'Cardiología']);

        $response->assertStatus(201);
        $this->assertDatabaseHas('specialties', ['name' => 'Cardiología']);
    }

    public function test_store_rechaza_nombre_duplicado(): void
    {
        $admin = User::factory()->create();
        Specialty::create(['name' => 'Pediatría', 'state' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => 'Pediatría']);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_destroy_elimina_la_especialidad(): void
    {
        $admin = User::factory()->create();
        $specialty = Specialty::create(['name' => 'Dermatología', 'state' => 1]);

        $response = $this->actingAs($admin)->deleteJson("/api/specialties/{$specialty->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('specialties', ['id' => $specialty->id]);
    }
}
```

Crear `tests/Feature/CityControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_by_department_retorna_solo_las_ciudades_de_ese_departamento(): void
    {
        $admin = User::factory()->create();
        $deptA = Department::create(['name' => 'Antioquia']);
        $deptB = Department::create(['name' => 'Cundinamarca']);
        $cityA = City::create(['name' => 'Medellín', 'department_id' => $deptA->id]);
        City::create(['name' => 'Bogotá', 'department_id' => $deptB->id]);

        $response = $this->actingAs($admin)->getJson("/api/departments/{$deptA->id}/cities");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($cityA->id, $response->json('data.0.id'));
    }
}
```

Crear `tests/Feature/DepartmentControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lista_departamentos(): void
    {
        $admin = User::factory()->create();
        Department::create(['name' => 'Antioquia']);
        Department::create(['name' => 'Valle del Cauca']);

        $response = $this->actingAs($admin)->getJson('/api/departments');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }
}
```

- [ ] **Step 2: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=SpecialtyControllerTest`
Run: `XDEBUG_MODE=off php artisan test --filter=CityControllerTest`
Run: `XDEBUG_MODE=off php artisan test --filter=DepartmentControllerTest`
Expected: PASS en los 3 archivos.

- [ ] **Step 3: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 11: Verificación de cobertura tras las Tareas 4-10

**Files:** ninguno — solo verificación.

**Interfaces:** consume el resultado combinado de las Tareas 4-10.

- [ ] **Step 1: Generar el reporte de cobertura**

Run: `XDEBUG_MODE=off composer test:coverage`
Expected: reporte generado sin error. Anotar el % de cobertura de `app/Http/Controllers` — no debe
haber bajado respecto a antes de estas tareas (el objetivo es que suba).

- [ ] **Step 2: Subir el umbral mínimo si el reporte lo permite**

Si la cobertura de `app/` ya supera 60-70%, en `composer.json` cambiar
`"--coverage --min=0"` por `"--coverage --min=60"` (subir gradualmente, no saltar directo a 85 —
eso se deja para después de las Tareas 13-15, que agregan más cobertura vía los Form Requests y el
Service).

**Checkpoint — no hacer commit.**

---

### Tarea 12: `declare(strict_types=1)` mecánico en todo `app/`

**Files:**
- Modify: los ~62 archivos `.php` bajo `app/` (obtener la lista exacta y actual con el comando del
  Step 1 — no usar una lista fija, puede haber cambiado con las tareas anteriores)

**Interfaces:** ninguna — es una transformación mecánica uniforme.

**Contexto verificado:** hoy 0 de 62 archivos tienen `declare(strict_types=1)`. Activar esto puede
exponer un `TypeError` donde antes había coerción silenciosa de tipos — por eso esta tarea va
**después** de las Tareas 4-11 (ya hay red de tests).

- [ ] **Step 1: Obtener la lista exacta de archivos a modificar**

Run: `grep -rL "declare(strict_types" app --include="*.php"`
Expected: lista de archivos sin `declare(strict_types=1)` (hoy, todos los de `app/`).

- [ ] **Step 2: Aplicar el cambio mecánico, en lotes de ~15 archivos**

Para cada archivo de la lista, la primera línea es `<?php` — insertar `declare(strict_types=1);`
como la línea siguiente, con una línea vacía después, ej.:
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;
```
(Si el archivo ya tiene una línea de `namespace` pegada a `<?php` sin línea vacía, mantener el
mismo estilo de espaciado que ya use el archivo — no es una regla nueva, solo insertar la
declaración.)

Procesar en 4 lotes: (1) `app/Models/*.php`, (2) `app/Http/Controllers/*.php` (primera mitad
alfabética), (3) `app/Http/Controllers/*.php` (segunda mitad), (4) el resto (`app/Services/`,
`app/Auth/`, `app/Console/Commands/`, `app/Providers/`).

- [ ] **Step 3: Correr la suite completa después de CADA lote**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde después de cada uno de los 4 lotes. Si algún test falla con `TypeError`,
es una coerción de tipo real que `strict_types` dejó de permitir silenciosamente — investigar el
caso puntual (probablemente un `string` numérico pasado donde se espera `int`, o viceversa) y
corregir el caller o el tipo del parámetro, no revertir `strict_types` en ese archivo.

- [ ] **Step 4: Confirmar los 62 archivos (o los que existan a esta altura) migrados**

Run: `grep -rL "declare(strict_types" app --include="*.php"`
Expected: lista vacía.

**Checkpoint — no hacer commit.**

---

### Tarea 13: Extraer `BeneficiarySyncService` de `AffiliateController`

**Files:**
- Create: `app/Services/BeneficiarySyncService.php`
- Modify: `app/Http/Controllers/AffiliateController.php` (`store()`, `update()`)
- Test: `tests/Feature/BeneficiarySyncServiceTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: `BeneficiarySyncService::sync(Affiliate $affiliate, array $beneficiariosRequest): void` —
  usado por `AffiliateController::store()`/`update()`.

**Contexto verificado:** `AffiliateController::store()`/`update()` recorren el array
`beneficiaries` del request haciendo `create`/`update`/`delete` según corresponda, mezclado con el
resto de la lógica del método. La firma exacta del array de beneficiarios y el modelo
`Beneficiary` deben confirmarse leyendo el bloque actual de `store()`/`update()` antes de mover el
código — este plan no reproduce esa lógica línea por línea porque no se transcribió en la
investigación previa; el ejecutor debe leer el bloque real (buscar `beneficiaries` en
`AffiliateController.php`) y moverlo tal cual al servicio, sin cambiar el comportamiento.

- [ ] **Step 1: Escribir el test que fija el contrato actual (antes de mover nada)**

Crear `tests/Feature/BeneficiarySyncServiceTest.php` con al menos 3 casos usando el endpoint HTTP
real (no llamando al servicio directo, porque todavía no existe):
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiarySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_agrega_un_beneficiario_nuevo(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['name' => 'Hijo Nuevo', 'id_card' => '111']],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('beneficiaries', ['affiliate_id' => $affiliate->id, 'name' => 'Hijo Nuevo']);
    }

    public function test_update_edita_un_beneficiario_existente(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $created = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['name' => 'Nombre Original', 'id_card' => '222']],
        ]);
        $beneficiaryId = \App\Models\Beneficiary::where('affiliate_id', $affiliate->id)->first()->id;

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['id' => $beneficiaryId, 'name' => 'Nombre Editado', 'id_card' => '222']],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('beneficiaries', ['id' => $beneficiaryId, 'name' => 'Nombre Editado']);
    }

    public function test_update_elimina_beneficiarios_no_incluidos_en_el_payload(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['name' => 'A Eliminar', 'id_card' => '333']],
        ]);
        $beneficiaryId = \App\Models\Beneficiary::where('affiliate_id', $affiliate->id)->first()->id;

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('beneficiaries', ['id' => $beneficiaryId]);
    }
}
```

- [ ] **Step 2: Correr los tests contra el código actual (referencia "antes")**

Run: `XDEBUG_MODE=off php artisan test --filter=BeneficiarySyncServiceTest`
Expected: PASS (fija el comportamiento actual antes de mover el código). Si algún test no pasa,
ajustar el payload/aserciones al comportamiento real observado, no al asumido — este paso existe
precisamente para descubrir la forma real del array si difiere de lo aquí escrito.

- [ ] **Step 3: Crear el servicio moviendo la lógica real (leída del controlador)**

Crear `app/Services/BeneficiarySyncService.php` con la firma:
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliate;

final class BeneficiarySyncService
{
    /**
     * @param array<int, array<string, mixed>> $beneficiariosRequest
     */
    public function sync(Affiliate $affiliate, array $beneficiariosRequest): void
    {
        // Mover aquí, tal cual, el bloque de create/update/delete que hoy vive
        // en AffiliateController::store()/update() para el array "beneficiaries".
    }
}
```
Mover el cuerpo real leyendo el bloque actual del controlador (buscar la sección que itera
`$request->beneficiaries` o similar) — copiar la lógica exacta, sin reescribirla ni "mejorarla" en
este paso.

- [ ] **Step 4: Migrar `AffiliateController` a usar el servicio**

Agregar al constructor de `AffiliateController` (crear uno si no existe, o extender el existente):
```php
public function __construct(private BeneficiarySyncService $beneficiarySync)
```
En `store()` y `update()`, reemplazar el bloque de sincronización de beneficiarios por:
```php
$this->beneficiarySync->sync($affiliate, $request->input('beneficiaries', []));
```

- [ ] **Step 5: Correr los tests y confirmar que siguen pasando**

Run: `XDEBUG_MODE=off php artisan test --filter=BeneficiarySyncServiceTest`
Expected: PASS — mismo comportamiento observable, ahora en un servicio separado.

- [ ] **Step 6: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde, incluyendo `AffiliateControllerTest::test_store_crea_beneficiarios_asociados`
(del plan 2026-08-07) si sigue existiendo.

**Checkpoint — no hacer commit.**

---

### Tarea 14: Form Request para `Affiliate` (preservando el código 400)

**Files:**
- Create: `app/Http/Requests/StoreAffiliateRequest.php`
- Create: `app/Http/Requests/UpdateAffiliateRequest.php`
- Modify: `app/Http/Controllers/AffiliateController.php` (`store()`, `update()`)

**Interfaces:**
- Depende de la Tarea 13 (el servicio de beneficiarios ya extraído, para que el controlador quede
  limpio antes de este cambio).
- Produce: `StoreAffiliateRequest`/`UpdateAffiliateRequest` — inyectados en los métodos del
  controlador en vez de `Request` genérico + `Validator::make()`.

**Contexto verificado:** el proyecto devuelve 400 en validación fallida (`Validator::make()` +
manejo manual), no el 422 default de Laravel. Un `FormRequest` normal devuelve 422 vía
`ValidationException` — hay que sobrescribir `failedValidation()` para mantener 400.

- [ ] **Step 1: Escribir el test que fija el código 400 actual**

Crear (o extender si ya existe) `tests/Feature/AffiliateControllerTest.php` con:
```php
public function test_store_retorna_400_no_422_en_validacion_fallida(): void
{
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/affiliates', []);

    $response->assertStatus(400);
}
```

- [ ] **Step 2: Correr el test como referencia "antes"**

Run: `XDEBUG_MODE=off php artisan test --filter=test_store_retorna_400_no_422_en_validacion_fallida`
Expected: PASS ya con el código actual (fija el contrato antes de migrar).

- [ ] **Step 3: Crear `StoreAffiliateRequest`**

Crear `app/Http/Requests/StoreAffiliateRequest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Copiar aquí, tal cual, el array de reglas actual de
        // AffiliateController::store() — leer el método real antes de copiar,
        // no inventar el array desde cero.
        return [];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors' => $validator->errors(),
        ], 400));
    }
}
```
Repetir el mismo patrón para `UpdateAffiliateRequest`, copiando las reglas reales de `update()`
(que son `sometimes|required|...` en los campos NOT NULL, per `CLAUDE.md`).

- [ ] **Step 4: Migrar el controlador**

En `AffiliateController::store(StoreAffiliateRequest $request)` y
`::update(UpdateAffiliateRequest $request, $id)`, eliminar el bloque `Validator::make(...)` +
chequeo manual de `->fails()` — los datos ya vienen validados por el Form Request; usar
`$request->validated()` en vez de `$request->all()`/`$request->only(...)` donde antes se leía el
array de campos.

- [ ] **Step 5: Correr el test y confirmar que sigue en 400**

Run: `XDEBUG_MODE=off php artisan test --filter=test_store_retorna_400_no_422_en_validacion_fallida`
Expected: PASS.

- [ ] **Step 6: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde — especialmente `AffiliateMovilValidationTest` y
`AffiliateStadeAuthorizationTest` (Tarea 1), que dependen del comportamiento de validación/
autorización de este mismo controlador.

**Checkpoint — no hacer commit.**

---

### Tarea 15: Form Requests para `Appointment` + `User`

**Files:**
- Create: `app/Http/Requests/StoreAppointmentRequest.php`, `UpdateAppointmentRequest.php`
- Create: `app/Http/Requests/StoreUserRequest.php`, `UpdateUserRequest.php`
- Modify: `app/Http/Controllers/AppointmentController.php`, `app/Http/Controllers/UserController.php`

**Interfaces:**
- Depende de la Tarea 5 (tests de `UserController` ya existen) y de los tests existentes de
  `AppointmentController` (del plan 2026-08-07) para tener red de seguridad.
- Produce: los 4 Form Requests, mismo patrón de `failedValidation()` → 400 que la Tarea 14.

**Contexto verificado:** validación real de `UserController::store()`/`update()` ya documentada en
la Tarea 5 de este plan. La de `AppointmentController` no se transcribió en la investigación previa
— el ejecutor debe leer el método real antes de copiar las reglas.

- [ ] **Step 1: Escribir/confirmar el test que fija el código 400 actual para ambos controladores**

Agregar a `tests/Feature/UserControllerCrudTest.php` (de la Tarea 5):
```php
public function test_store_retorna_400_no_422_en_validacion_fallida(): void
{
    $admin = User::factory()->create();

    $response = $this->actingAs($admin)->postJson('/api/users', []);

    $response->assertStatus(400);
}
```
Agregar un test equivalente a `tests/Feature/AppointmentControllerTest.php` si no existe ya uno
para el caso de payload vacío.

- [ ] **Step 2: Correr ambos como referencia "antes"**

Run: `XDEBUG_MODE=off php artisan test --filter=test_store_retorna_400_no_422_en_validacion_fallida`
Expected: PASS con el código actual.

- [ ] **Step 3: Crear los 4 Form Requests**

Mismo patrón exacto de la Tarea 14 (`authorize(): true`, `rules()` copiando el array real del
controlador correspondiente, `failedValidation()` lanzando `HttpResponseException` con 400).

- [ ] **Step 4: Migrar ambos controladores**

`AppointmentController::store(StoreAppointmentRequest $request)`,
`::update(UpdateAppointmentRequest $request, Appointment $appointment)`;
`UserController::store(StoreUserRequest $request)`, `::update(UpdateUserRequest $request, $id)`.
Eliminar los bloques `Validator::make(...)` correspondientes, usar `$request->validated()`.

- [ ] **Step 5: Correr los tests y confirmar 400 preservado**

Run: `XDEBUG_MODE=off php artisan test --filter=UserControllerCrudTest`
Run: `XDEBUG_MODE=off php artisan test --filter=AppointmentControllerTest`
Expected: PASS.

- [ ] **Step 6: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 16: Pasada de PHPDoc WHY en los puntos ya identificados

**Files:**
- Modify: `app/Http/Controllers/AffiliateController.php` (método `update()`, junto al fix de la Tarea 1)
- Modify: `app/Http/Controllers/AppointmentController.php` (método `index()`, normalización de `owner`)
- Modify: `app/Http/Controllers/RenovationController.php` (método `store()`, reactivación de `stade`)

**Interfaces:** ninguna — solo documentación.

- [ ] **Step 1: `AffiliateController::update()`**

Justo antes del bloque de la Tarea 1 (chequeo de `stade`), agregar:
```php
        // Solo el super admin puede cambiar `stade` manualmente — el flujo normal
        // es que el cron lo inactive al vencer y la renovación lo reactive. Ver
        // regla de negocio en CLAUDE.md ("Regla de acceso para cambio manual de stade").
```

- [ ] **Step 2: `AppointmentController::index()`**

Antes del bloque que calcula `owner`, agregar (si no existe ya un comentario equivalente):
```php
        // `owner` es un campo calculado, nunca persistido: `affiliate` si type=1,
        // `beneficiary` si type=2. Se recalcula en cada index()/show() porque el
        // significado de `afi_code` cambia según `type` (ver CLAUDE.md).
```

- [ ] **Step 3: `RenovationController::store()`**

Antes del `Affiliate::where(...)->update(['stade' => 1])`, agregar:
```php
        // Reactiva el afiliado solo si estaba inactivo por vencimiento (stade=2).
        // Es un respaldo de backend: el frontend también envía stade=1 al renovar,
        // pero esto cubre llamadas directas al endpoint de renovaciones.
```

- [ ] **Step 4: Correr la suite para confirmar que son solo comentarios**

Run: `XDEBUG_MODE=off php artisan test`
Expected: sin cambios de comportamiento, todo en verde.

**Checkpoint — no hacer commit.**

---

### Tarea 17: Verificación final integrada

**Files:** ninguno.

**Interfaces:** consume el resultado combinado de las Tareas 1-16.

- [ ] **Step 1: Suite completa en verde**

Run: `XDEBUG_MODE=off php artisan test`
Expected: cero fallos, incluyendo todos los tests nuevos de este plan más los del plan 2026-08-07.

- [ ] **Step 2: Reporte de cobertura final**

Run: `XDEBUG_MODE=off composer test:coverage`
Expected: cobertura de `app/` visiblemente mayor que al inicio de este plan. Anotar el % final —
si ya supera 85%, subir `--min` en `composer.json` a ese valor real; si no, dejar constancia de
cuánto falta y para qué módulos (no forzar el umbral por encima de la realidad).

- [ ] **Step 3: Confirmar que `declare(strict_types=1)` está en el 100% de `app/`**

Run: `grep -rL "declare(strict_types" app --include="*.php"`
Expected: lista vacía.

- [ ] **Step 4: Revisar el diff completo antes de decidir integrar**

```bash
git diff --stat
git diff
```
Expected: cambios concentrados en los archivos de este plan, sin modificaciones accidentales a
`config/auth.php`, `config/cors.php`, ni a nada ya resuelto en sesiones anteriores.

**Checkpoint final — el usuario decide cuándo y cómo commitear/pushear este conjunto de cambios.**
