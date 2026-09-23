# Auditoría de acciones (`regist_actions`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Hacer que `regist_actions` empiece a guardar registros reales — creación/edición de médicos, especialidades, franquicias, asesores y convenios, y el cambio de `stade` (activo/inactivo) en afiliados — cada uno con el usuario que lo hizo.

**Architecture:** Un servicio único `App\Services\RegistActionLogger` (mismo patrón que `WhatsAppClient`/`IdCardLookup`) se inyecta por constructor en los 6 controladores afectados y se llama justo después de persistir el modelo. La tabla gana una columna `user_id` (nullable, `nullOnDelete`). Tres `action_type`: `'I'` (creación), `'U'` (edición general), `'E'` (cambio específico de estado) — sin columna nueva de "tipo de acción".

**Tech Stack:** Laravel, PHPUnit (SQLite en tests, `RefreshDatabase`), MySQL en producción.

**Spec:** [docs/superpowers/specs/2026-09-22-audit-log-regist-actions-design.md](../specs/2026-09-22-audit-log-regist-actions-design.md)

## Global Constraints

- `action_type` solo admite `'I'`, `'U'`, `'E'` — ningún otro valor (no hay `'D'`, no se audita borrado).
- `target_table` es siempre el nombre literal de tabla en minúsculas: `doctors`, `specialties`, `users`, `counselors`, `agreements`, `affiliates`.
- `regist_actions.user_id` usa `nullOnDelete()`, nunca `cascade` — el rastro de auditoría sobrevive al usuario que lo generó.
- Afiliados: solo se audita el cambio de `stade` en `update()`, nunca `store()`, nunca el resto de campos, y sin gate de rol (la condición es únicamente que `stade` haya cambiado).
- Cuando en una misma petición cambia el campo de estado y también otros campos, se loguea una sola fila con `'E'` — nunca una fila adicional `'U'`.
- No se implementa lectura (`RegistActionController::index()`/`show()`) en este plan — fuera de alcance según el spec.

## Review Focus

- Guardar un afiliado nuevo (`store()`) no debe generar ninguna fila en `regist_actions`, aunque cree con `stade` inicial — solo `update()` audita afiliados.
- Una petición de `update()` que cambia el campo de estado y otro campo a la vez debe producir exactamente una fila (`'E'`), no dos.
- Una petición rechazada por validación (400) o autorización (403) no debe dejar ninguna fila en `regist_actions` — el logger solo se llama después de persistir con éxito.
- Los cambios automáticos de `stade` que no pasan por `AffiliateController::update()` (el comando `affiliates:update-expired` y la reactivación en `RenovationController`) no deben generar fila alguna — no hay un usuario detrás de esa acción.
- Borrar el usuario que aparece en `regist_actions.user_id` no debe borrar ni romper la fila de auditoría — `user_id` debe quedar en `null` (`nullOnDelete`, nunca `cascade`).

---

## Task 1: Columna `user_id` en `regist_actions` + modelo corregido

**Files:**
- Create: `database/migrations/2026_09_22_000002_add_user_id_to_regist_actions_table.php`
- Modify: `app/Models/RegistAction.php`
- Test: `tests/Feature/RegistActionModelTest.php`

**Interfaces:**
- Produces: tabla `regist_actions` con columna `user_id` (nullable, FK → `users.id`, `nullOnDelete`); `RegistAction::$fillable = ['action_type', 'target_table', 'table_id', 'user_id']`. Las tareas siguientes escriben filas con estas 4 columnas.

- [ ] **Step 1: Escribir el test que falla**

```php
<?php

namespace Tests\Feature;

use App\Models\RegistAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistActionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_crear_con_las_4_columnas_reales(): void
    {
        $user = User::factory()->create();

        $action = RegistAction::create([
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 1,
            'user_id'      => $user->id,
        ]);

        $this->assertDatabaseHas('regist_actions', [
            'id'           => $action->id,
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 1,
            'user_id'      => $user->id,
        ]);
    }

    public function test_borrar_el_usuario_deja_user_id_en_null_sin_borrar_el_registro(): void
    {
        $user = User::factory()->create();
        $action = RegistAction::create([
            'action_type'  => 'U',
            'target_table' => 'specialties',
            'table_id'     => 5,
            'user_id'      => $user->id,
        ]);

        $user->delete();

        $this->assertDatabaseHas('regist_actions', [
            'id'      => $action->id,
            'user_id' => null,
        ]);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/RegistActionModelTest.php --process-isolation`
Expected: FAIL — `user_id` no existe como columna (error SQL) y/o `MassAssignmentException` porque `$fillable` no incluye `user_id`.

- [ ] **Step 3: Crear la migración**

`database/migrations/2026_09_22_000002_add_user_id_to_regist_actions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regist_actions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('table_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regist_actions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
```

- [ ] **Step 4: Corregir el modelo**

`app/Models/RegistAction.php` completo:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_type',
        'target_table',
        'table_id',
        'user_id',
    ];
}
```

- [ ] **Step 5: Correr el test y verificar que pasa**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/RegistActionModelTest.php --process-isolation`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_22_000002_add_user_id_to_regist_actions_table.php app/Models/RegistAction.php tests/Feature/RegistActionModelTest.php
git commit -m "feat(regist-actions): add user_id column and fix model fillable"
```

---

## Task 2: Servicio `RegistActionLogger`

**Files:**
- Create: `app/Services/RegistActionLogger.php`
- Test: `tests/Feature/RegistActionLoggerTest.php`

**Interfaces:**
- Consumes: `App\Models\RegistAction::create()` (Task 1), `auth()->id()`.
- Produces: `App\Services\RegistActionLogger` con `created(string $table, int $id): void`, `updated(string $table, int $id): void`, `statusChanged(string $table, int $id): void`. Las tareas 3-8 inyectan esta clase por constructor y llaman a estos tres métodos exactos.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/RegistActionLoggerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RegistActionLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistActionLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_guarda_action_type_i_con_el_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new RegistActionLogger())->created('doctors', 42);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 42,
            'user_id'      => $user->id,
        ]);
    }

    public function test_updated_guarda_action_type_u(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new RegistActionLogger())->updated('specialties', 7);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'specialties',
            'table_id'     => 7,
            'user_id'      => $user->id,
        ]);
    }

    public function test_status_changed_guarda_action_type_e(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new RegistActionLogger())->statusChanged('affiliates', 3);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'affiliates',
            'table_id'     => 3,
            'user_id'      => $user->id,
        ]);
    }

    public function test_sin_usuario_autenticado_guarda_user_id_null(): void
    {
        (new RegistActionLogger())->created('doctors', 1);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 1,
            'user_id'      => null,
        ]);
    }
}
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/RegistActionLoggerTest.php --process-isolation`
Expected: FAIL — `App\Services\RegistActionLogger` no existe (`Class not found`).

- [ ] **Step 3: Implementar el servicio**

`app/Services/RegistActionLogger.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RegistAction;

class RegistActionLogger
{
    public function created(string $table, int $id): void
    {
        $this->log('I', $table, $id);
    }

    public function updated(string $table, int $id): void
    {
        $this->log('U', $table, $id);
    }

    public function statusChanged(string $table, int $id): void
    {
        $this->log('E', $table, $id);
    }

    private function log(string $actionType, string $table, int $id): void
    {
        RegistAction::create([
            'action_type'  => $actionType,
            'target_table' => $table,
            'table_id'     => $id,
            'user_id'      => auth()->id(),
        ]);
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/RegistActionLoggerTest.php --process-isolation`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/RegistActionLogger.php tests/Feature/RegistActionLoggerTest.php
git commit -m "feat(regist-actions): add RegistActionLogger service"
```

---

## Task 3: Integrar en `DoctorController`

**Files:**
- Modify: `app/Http/Controllers/DoctorController.php`
- Modify: `tests/Feature/DoctorControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\RegistActionLogger::created()`/`updated()`/`statusChanged()` (Task 2).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar al final de la clase en `tests/Feature/DoctorControllerTest.php` (antes del `}` de cierre, línea 90):

```php
    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/doctors', $this->payloadValido($referencia));
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_store_con_validacion_fallida_no_registra_regist_action(): void
    {
        $admin = User::factory()->create();
        $referencia = Doctor::factory()->create();
        $payload = $this->payloadValido($referencia);
        $payload['movil'] = '123';

        $this->actingAs($admin)->postJson('/api/doctors', $payload);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_update_de_campo_normal_registra_action_type_u(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create(['state' => 1]);

        $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'value_agreement' => 120000,
        ]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'doctors',
            'table_id'     => $doctor->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_que_cambia_state_registra_action_type_e_una_sola_vez(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create(['state' => 1]);

        $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'state' => 2,
            'value_agreement' => 130000,
        ]);

        $this->assertDatabaseCount('regist_actions', 1);
        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'doctors',
            'table_id'     => $doctor->id,
        ]);
    }
```

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/DoctorControllerTest.php --process-isolation`
Expected: FAIL en los 4 tests nuevos — `regist_actions` sigue vacía porque el controlador no llama al logger todavía. (`test_update_de_campo_normal_registra_action_type_u` y `test_update_que_cambia_state_registra_action_type_e_una_sola_vez` también fallan porque `assertDatabaseHas` no encuentra fila.)

- [ ] **Step 3: Conectar `RegistActionLogger` en el controlador**

Import (`app/Http/Controllers/DoctorController.php:7-10`, agregar línea antes de `Illuminate\Http\Request`):

```php
use App\Http\Requests\UpdateDoctorRequest;
use App\Models\Doctor;
use App\Services\RegistActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
```

Constructor (insertar justo después de `class DoctorController extends Controller\n{`, línea 13):

```php
class DoctorController extends Controller
{
    public function __construct(private RegistActionLogger $registActionLogger)
    {
    }

```

En `store()`, reemplazar (línea 188-193):

```php
        $doctor = Doctor::create([
            'name'            => $request->name,
            'lastname'        => $request->lastname,
            'email'           => $request->email,
            'specialty_id'    => $request->specialty_id,
            'city_id'         => $request->city_id,
            'phone'           => $request->phone,
            'movil'           => $request->movil,
            'address'         => $request->address,
            'secretary_name'  => $request->secretary_name,
            'value_agreement' => $request->value_agreement ?? 0,
            'state'           => $request->state ?? 1,
        ]);

        return response()->json([
            'message' => 'Médico creado correctamente',
            'data' => $doctor,
        ], 201);
```

por:

```php
        $doctor = Doctor::create([
            'name'            => $request->name,
            'lastname'        => $request->lastname,
            'email'           => $request->email,
            'specialty_id'    => $request->specialty_id,
            'city_id'         => $request->city_id,
            'phone'           => $request->phone,
            'movil'           => $request->movil,
            'address'         => $request->address,
            'secretary_name'  => $request->secretary_name,
            'value_agreement' => $request->value_agreement ?? 0,
            'state'           => $request->state ?? 1,
        ]);

        $this->registActionLogger->created('doctors', $doctor->id);

        return response()->json([
            'message' => 'Médico creado correctamente',
            'data' => $doctor,
        ], 201);
```

En `update()`, reemplazar (línea 231-236):

```php
        $doctor->update($request->validated());

        return response()->json([
            'message' => 'Médico actualizado correctamente',
            'data' => $doctor,
        ], 200);
```

por:

```php
        $doctor->update($request->validated());

        if ($doctor->wasChanged('state')) {
            $this->registActionLogger->statusChanged('doctors', $doctor->id);
        } else {
            $this->registActionLogger->updated('doctors', $doctor->id);
        }

        return response()->json([
            'message' => 'Médico actualizado correctamente',
            'data' => $doctor,
        ], 200);
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/DoctorControllerTest.php --process-isolation`
Expected: PASS (todos los tests del archivo, incluidos los 4 nuevos)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DoctorController.php tests/Feature/DoctorControllerTest.php
git commit -m "feat(regist-actions): audit doctor create/update"
```

---

## Task 4: Integrar en `SpecialtyController`

**Files:**
- Modify: `app/Http/Controllers/SpecialtyController.php`
- Modify: `tests/Feature/SpecialtyControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\RegistActionLogger` (Task 2).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar al final de la clase en `tests/Feature/SpecialtyControllerTest.php` (antes del `}` de cierre, línea 67):

```php
    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/specialties', ['name' => 'Oncología']);
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'specialties',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_store_no_autorizado_no_registra_regist_action(): void
    {
        $asesor = User::factory()->create(['type' => 2]);

        $this->actingAs($asesor)->postJson('/api/specialties', ['name' => 'Oncología']);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_update_de_state_registra_action_type_e(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $specialty = Specialty::create(['name' => 'Urología', 'state' => 1]);

        $this->actingAs($admin)->putJson("/api/specialties/{$specialty->id}", ['state' => 0]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'specialties',
            'table_id'     => $specialty->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_de_nombre_sin_cambiar_state_registra_action_type_u(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $specialty = Specialty::create(['name' => 'Endocrinología', 'state' => 1]);

        $this->actingAs($admin)->putJson("/api/specialties/{$specialty->id}", ['name' => 'Endocrinología Clínica']);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'specialties',
            'table_id'     => $specialty->id,
        ]);
    }
```

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/SpecialtyControllerTest.php --process-isolation`
Expected: FAIL en los 4 tests nuevos.

- [ ] **Step 3: Conectar `RegistActionLogger` en el controlador**

Import (`app/Http/Controllers/SpecialtyController.php:7-9`):

```php
use App\Models\Specialty;
use App\Services\RegistActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
```

Constructor (después de `class SpecialtyController extends Controller\n{`, línea 11):

```php
class SpecialtyController extends Controller
{
    public function __construct(private RegistActionLogger $registActionLogger)
    {
    }

```

En `store()`, reemplazar (línea 62-70):

```php
        $specialty = Specialty::create([
            'name'  => $request->name,
            'state' => $request->state ?? 1,
        ]);

        return response()->json([
            'message' => 'Especialidad creada correctamente',
            'data' => $specialty,
        ], 201);
```

por:

```php
        $specialty = Specialty::create([
            'name'  => $request->name,
            'state' => $request->state ?? 1,
        ]);

        $this->registActionLogger->created('specialties', $specialty->id);

        return response()->json([
            'message' => 'Especialidad creada correctamente',
            'data' => $specialty,
        ], 201);
```

En `update()`, reemplazar (línea 121-129):

```php
        if ($request->filled('name')) $specialty->name = $request->name;
        if ($request->filled('state') || $request->has('state')) $specialty->state = $request->state;

        $specialty->save();

        return response()->json([
            'message' => 'Especialidad actualizada correctamente',
            'data' => $specialty,
        ], 200);
```

por:

```php
        if ($request->filled('name')) $specialty->name = $request->name;
        if ($request->filled('state') || $request->has('state')) $specialty->state = $request->state;

        $specialty->save();

        if ($specialty->wasChanged('state')) {
            $this->registActionLogger->statusChanged('specialties', $specialty->id);
        } else {
            $this->registActionLogger->updated('specialties', $specialty->id);
        }

        return response()->json([
            'message' => 'Especialidad actualizada correctamente',
            'data' => $specialty,
        ], 200);
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/SpecialtyControllerTest.php --process-isolation`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SpecialtyController.php tests/Feature/SpecialtyControllerTest.php
git commit -m "feat(regist-actions): audit specialty create/update"
```

---

## Task 5: Integrar en `UserController` (franquicias)

**Files:**
- Modify: `app/Http/Controllers/UserController.php`
- Modify: `tests/Feature/UserControllerCrudTest.php`

**Interfaces:**
- Consumes: `App\Services\RegistActionLogger` (Task 2).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar al final de la clase en `tests/Feature/UserControllerCrudTest.php`:

```php
    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create();
        $cityId = User::factory()->create()->city_id;

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'nit' => '7776665552',
            'name' => 'Franquicia Auditada',
            'email' => 'auditada@example.com',
            'user' => 'franquiciaauditada',
            'password' => 'secret123',
            'city_id' => $cityId,
        ]);
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'users',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_de_state_registra_action_type_e(): void
    {
        $admin = User::factory()->create();
        $franquicia = User::factory()->create(['state' => 1]);

        $this->actingAs($admin)->patchJson("/api/users/{$franquicia->id}", ['state' => 2]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'users',
            'table_id'     => $franquicia->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_sin_cambiar_state_registra_action_type_u(): void
    {
        $admin = User::factory()->create();
        $franquicia = User::factory()->create(['state' => 1, 'phone' => '6041110000']);

        $this->actingAs($admin)->patchJson("/api/users/{$franquicia->id}", ['phone' => '6042223333']);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'users',
            'table_id'     => $franquicia->id,
        ]);
    }
```

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/UserControllerCrudTest.php --process-isolation`
Expected: FAIL en los 3 tests nuevos.

- [ ] **Step 3: Conectar `RegistActionLogger` en el controlador**

Import (`app/Http/Controllers/UserController.php:7-12`):

```php
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\RegistActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
```

Constructor (después de `class UserController extends Controller\n{`, línea 15):

```php
class UserController extends Controller
{
    public function __construct(private RegistActionLogger $registActionLogger)
    {
    }

```

En `store()`, reemplazar (línea 43-62):

```php
        $user = User::create([
            'nit' => $data['nit'],
            'name' => $data['name'],
            'contact' => $data['contact'] ?? null,
            'phone' => $data['phone'] ?? null,
            'movil' => $data['movil'] ?? null,
            'address' => $data['address'] ?? null,
            'date_afi' => $data['date_afi'] ?? null,
            'email' => $data['email'],
            'user' => $data['user'],
            'password' => Hash::make($data['password']),
            'state' => $data['state'] ?? 1,
            'city_id' => $data['city_id'],
            'type' => $data['type'] ?? 2,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => $user,
        ], 201);
```

por:

```php
        $user = User::create([
            'nit' => $data['nit'],
            'name' => $data['name'],
            'contact' => $data['contact'] ?? null,
            'phone' => $data['phone'] ?? null,
            'movil' => $data['movil'] ?? null,
            'address' => $data['address'] ?? null,
            'date_afi' => $data['date_afi'] ?? null,
            'email' => $data['email'],
            'user' => $data['user'],
            'password' => Hash::make($data['password']),
            'state' => $data['state'] ?? 1,
            'city_id' => $data['city_id'],
            'type' => $data['type'] ?? 2,
        ]);

        $this->registActionLogger->created('users', $user->id);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => $user,
        ], 201);
```

En `update()`, reemplazar (línea 128-133):

```php
        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => $user,
        ], 200);
```

por:

```php
        $user->save();

        if ($user->wasChanged('state')) {
            $this->registActionLogger->statusChanged('users', $user->id);
        } else {
            $this->registActionLogger->updated('users', $user->id);
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => $user,
        ], 200);
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/UserControllerCrudTest.php --process-isolation`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/UserController.php tests/Feature/UserControllerCrudTest.php
git commit -m "feat(regist-actions): audit franchise (users) create/update"
```

---

## Task 6: Integrar en `CounselorController`

**Files:**
- Modify: `app/Http/Controllers/CounselorController.php`
- Modify: `tests/Feature/CounselorControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\RegistActionLogger` (Task 2).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar al final de la clase en `tests/Feature/CounselorControllerTest.php`:

```php
    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create();
        $response = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'counselors',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_de_state_registra_action_type_e(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $this->actingAs($admin)->patchJson("/api/counselors/{$id}", ['state' => 2]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'counselors',
            'table_id'     => $id,
        ]);
    }

    public function test_update_sin_cambiar_state_registra_action_type_u(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $this->actingAs($admin)->patchJson("/api/counselors/{$id}", ['phone' => '6041112233']);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'counselors',
            'table_id'     => $id,
        ]);
    }
```

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/CounselorControllerTest.php --process-isolation`
Expected: FAIL en los 3 tests nuevos.

- [ ] **Step 3: Conectar `RegistActionLogger` en el controlador**

Import (`app/Http/Controllers/CounselorController.php:7-12`):

```php
use App\Http\Requests\UpdateCounselorRequest;
use App\Models\Counselor;
use App\Services\RegistActionLogger;
use App\Support\IdCardLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
```

Constructor (después de `class CounselorController extends Controller\n{`, línea 15, antes del método estático `typeContraValues`):

```php
class CounselorController extends Controller
{
    public function __construct(private RegistActionLogger $registActionLogger)
    {
    }

    // Public and static so UpdateCounselorRequest can reuse the same list
```

En `store()`, reemplazar (línea 86-110):

```php
        $counselor = Counselor::create([
            'name'           => $request->name,
            'lastname'       => $request->lastname,
            'id_card'        => $request->id_card,
            'address'        => $request->address,
            'date_admission' => $request->date_admission,
            'type_contra'    => $request->type_contra,

            'email'          => $request->email,
            'password'       => $request->password ? Hash::make($request->password) : null,

            'rol'            => $request->rol,
            'phone'          => $request->phone,
            'movil'          => $request->movil,

            'state'          => $request->state ?? 1,

            'city_id'        => $request->city_id,
            'user_id'        => $request->user_id,
        ]);

        return response()->json([
            'message' => 'Vendedor creado correctamente',
            'data' => $counselor,
        ], 201);
```

por:

```php
        $counselor = Counselor::create([
            'name'           => $request->name,
            'lastname'       => $request->lastname,
            'id_card'        => $request->id_card,
            'address'        => $request->address,
            'date_admission' => $request->date_admission,
            'type_contra'    => $request->type_contra,

            'email'          => $request->email,
            'password'       => $request->password ? Hash::make($request->password) : null,

            'rol'            => $request->rol,
            'phone'          => $request->phone,
            'movil'          => $request->movil,

            'state'          => $request->state ?? 1,

            'city_id'        => $request->city_id,
            'user_id'        => $request->user_id,
        ]);

        $this->registActionLogger->created('counselors', $counselor->id);

        return response()->json([
            'message' => 'Vendedor creado correctamente',
            'data' => $counselor,
        ], 201);
```

En `update()`, reemplazar (línea 160-166):

```php
        $counselor->update($data);

        return response()->json([
            'message' => 'Vendedor actualizado correctamente',
            'data' => $counselor,
        ], 200);
```

por:

```php
        $counselor->update($data);

        if ($counselor->wasChanged('state')) {
            $this->registActionLogger->statusChanged('counselors', $counselor->id);
        } else {
            $this->registActionLogger->updated('counselors', $counselor->id);
        }

        return response()->json([
            'message' => 'Vendedor actualizado correctamente',
            'data' => $counselor,
        ], 200);
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/CounselorControllerTest.php --process-isolation`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/CounselorController.php tests/Feature/CounselorControllerTest.php
git commit -m "feat(regist-actions): audit counselor create/update"
```

---

## Task 7: Integrar en `AgreementController`

**Files:**
- Modify: `app/Http/Controllers/AgreementController.php`
- Modify: `tests/Feature/AgreementControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\RegistActionLogger` (Task 2).

- [ ] **Step 1: Escribir los tests que fallan**

Agregar al final de la clase en `tests/Feature/AgreementControllerTest.php`:

```php
    public function test_store_registra_regist_action_de_creacion(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $response->json('data.id');

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'agreements',
            'table_id'     => $id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_de_state_registra_action_type_e(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $payload = $this->payloadValido();
        $payload['state'] = 0;
        $this->actingAs($admin)->putJson("/api/agreements/{$id}", $payload);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'agreements',
            'table_id'     => $id,
        ]);
    }

    public function test_update_sin_cambiar_state_registra_action_type_u(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $created = $this->actingAs($admin)->postJson('/api/agreements', $this->payloadValido());
        $id = $created->json('data.id');

        $payload = $this->payloadValido();
        $payload['name'] = 'Convenio Editado';
        $this->actingAs($admin)->putJson("/api/agreements/{$id}", $payload);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'agreements',
            'table_id'     => $id,
        ]);
    }
```

- [ ] **Step 2: Correr los tests y verificar que fallan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/AgreementControllerTest.php --process-isolation`
Expected: FAIL en los 3 tests nuevos.

- [ ] **Step 3: Conectar `RegistActionLogger` en el controlador**

Import (`app/Http/Controllers/AgreementController.php:7-9`):

```php
use App\Models\Agreement;
use App\Services\RegistActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
```

Constructor (después de `class AgreementController extends Controller\n{`, línea 11):

```php
class AgreementController extends Controller
{
    public function __construct(private RegistActionLogger $registActionLogger)
    {
    }

```

En `store()`, reemplazar (línea 58-68):

```php
        $agreement = Agreement::create([
            'name'    => $request->name,
            'amount'  => $request->amount,
            'state'   => $request->state,
            'city_id' => $request->city_id,
        ]);

        return response()->json([
            'message' => 'Convenio creado correctamente',
            'data' => $agreement,
        ], 201);
```

por:

```php
        $agreement = Agreement::create([
            'name'    => $request->name,
            'amount'  => $request->amount,
            'state'   => $request->state,
            'city_id' => $request->city_id,
        ]);

        $this->registActionLogger->created('agreements', $agreement->id);

        return response()->json([
            'message' => 'Convenio creado correctamente',
            'data' => $agreement,
        ], 201);
```

En `update()`, reemplazar (línea 123-133):

```php
        $agreement->name = $request->name;
        $agreement->amount = $request->amount;
        $agreement->state = $request->state;
        $agreement->city_id = $request->city_id;

        $agreement->save();

        return response()->json([
            'message' => 'Convenio actualizado correctamente',
            'data' => $agreement,
        ], 200);
```

por:

```php
        $agreement->name = $request->name;
        $agreement->amount = $request->amount;
        $agreement->state = $request->state;
        $agreement->city_id = $request->city_id;

        $agreement->save();

        if ($agreement->wasChanged('state')) {
            $this->registActionLogger->statusChanged('agreements', $agreement->id);
        } else {
            $this->registActionLogger->updated('agreements', $agreement->id);
        }

        return response()->json([
            'message' => 'Convenio actualizado correctamente',
            'data' => $agreement,
        ], 200);
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/AgreementControllerTest.php --process-isolation`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AgreementController.php tests/Feature/AgreementControllerTest.php
git commit -m "feat(regist-actions): audit agreement create/update"
```

---

## Task 8: Integrar el caso especial de afiliados (`stade`)

**Files:**
- Modify: `app/Http/Controllers/AffiliateController.php`
- Modify: `tests/Feature/AffiliateStadeAuthorizationTest.php`
- Test: `tests/Feature/AffiliateRegistActionTest.php`

**Interfaces:**
- Consumes: `App\Services\RegistActionLogger` (Task 2).

- [ ] **Step 1: Escribir los tests que fallan**

Crear `tests/Feature/AffiliateRegistActionTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateRegistActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_no_registra_regist_action(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $referencia = Affiliate::factory()->create();

        // Mismo payload que test_store_crea_afiliado_con_datos_validos en
        // AffiliateControllerTest — un store() válido y completo.
        $this->actingAs($admin)->postJson('/api/affiliates', [
            'counselor_id'       => $referencia->counselor_id,
            'name'               => 'Nuevo',
            'lastname'           => 'Afiliado',
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
            'movil'              => '3001234567',
        ]);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_update_que_cambia_stade_registra_action_type_e(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", ['stade' => 2]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'affiliates',
            'table_id'     => $affiliate->id,
            'user_id'      => $admin->id,
        ]);
    }

    public function test_update_sin_stade_no_registra_regist_action(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", ['name' => 'Nombre Editado']);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_asesor_no_puede_cambiar_stade_y_no_registra_regist_action(): void
    {
        $asesor = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        // El controlador descarta `stade` del payload para roles no super admin
        // (ver AffiliateController::update), así que el valor no cambia y
        // wasChanged('stade') es false — no hay nada que auditar.
        $this->actingAs($asesor)->patchJson("/api/affiliates/{$affiliate->id}", ['stade' => 2]);

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_cron_de_vencimiento_no_registra_regist_action(): void
    {
        Affiliate::factory()->create([
            'stade' => 1,
            'validity_end' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('affiliates:update-expired');

        $this->assertDatabaseCount('regist_actions', 0);
    }

    public function test_renovacion_que_reactiva_no_registra_regist_action(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 2]);

        $this->actingAs($admin)->postJson('/api/renovations', [
            'affiliate_id' => $affiliate->id,
            'date_ini'     => now()->toDateString(),
            'date_end'     => now()->addYear()->toDateString(),
            'date_payment' => now()->toDateString(),
            'value'        => 100000,
        ]);

        $this->assertDatabaseCount('regist_actions', 0);
    }
}
```

Agregar también al final de `tests/Feature/AffiliateStadeAuthorizationTest.php` (antes del `}` de cierre, línea 92):

```php

    public function test_super_admin_cambia_stade_y_queda_registrado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['stade' => 1]);

        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", ['stade' => 2]);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'affiliates',
            'table_id'     => $affiliate->id,
            'user_id'      => $admin->id,
        ]);
    }
```

- [ ] **Step 2: Correr los tests y verificar el estado esperado**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/AffiliateRegistActionTest.php tests/Feature/AffiliateStadeAuthorizationTest.php --process-isolation`
Expected: `test_update_que_cambia_stade_registra_action_type_e` y `test_super_admin_cambia_stade_y_queda_registrado` FALLAN (no hay fila en `regist_actions` porque el controlador no llama al logger todavía). El resto de tests nuevos (que esperan 0 filas) PASAN ya desde antes de tocar el controlador — son pines de comportamiento negativo, no cambian con este paso.

- [ ] **Step 3: Conectar `RegistActionLogger` en `AffiliateController::update()`**

Import (`app/Http/Controllers/AffiliateController.php:7-15`):

```php
use App\Http\Requests\StoreAffiliateRequest;
use App\Http\Requests\UpdateAffiliateRequest;
use App\Models\Affiliate;
use App\Services\BeneficiarySyncService;
use App\Services\RegistActionLogger;
use App\Support\IdCardLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
```

Constructor (`app/Http/Controllers/AffiliateController.php:19-21`), reemplazar:

```php
    public function __construct(private BeneficiarySyncService $beneficiarySync)
    {
    }
```

por:

```php
    public function __construct(
        private BeneficiarySyncService $beneficiarySync,
        private RegistActionLogger $registActionLogger,
    ) {
    }
```

En `update()`, reemplazar (línea 138-142):

```php
        $affiliate->update(Arr::except($request->validated(), $excludedFields));

        if ($request->has('beneficiaries') && is_array($request->beneficiaries)) {
            $this->beneficiarySync->sync($affiliate, $request->beneficiaries);
        }
```

por:

```php
        $affiliate->update(Arr::except($request->validated(), $excludedFields));

        if ($affiliate->wasChanged('stade')) {
            $this->registActionLogger->statusChanged('affiliates', $affiliate->id);
        }

        if ($request->has('beneficiaries') && is_array($request->beneficiaries)) {
            $this->beneficiarySync->sync($affiliate, $request->beneficiaries);
        }
```

- [ ] **Step 4: Correr los tests y verificar que pasan**

Run: `XDEBUG_MODE=off php artisan test tests/Feature/AffiliateRegistActionTest.php tests/Feature/AffiliateStadeAuthorizationTest.php --process-isolation`
Expected: PASS (todos)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AffiliateController.php tests/Feature/AffiliateRegistActionTest.php tests/Feature/AffiliateStadeAuthorizationTest.php
git commit -m "feat(regist-actions): audit affiliate stade toggle regardless of role"
```

---

## Task 9: Verificación final de la suite completa

**Files:** ninguno (solo verificación)

- [ ] **Step 1: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test --process-isolation`
Expected: PASS — todos los tests existentes más los ~20 nuevos de este plan, 0 fallos.

- [ ] **Step 2: Confirmar `migrate:fresh` limpio**

Run: `php artisan migrate:fresh --env=testing`
Expected: corre sin errores, incluida la nueva migración `2026_09_22_000002_add_user_id_to_regist_actions_table`.

- [ ] **Step 3: Revisar `git status` y `git log` de la rama**

Run: `git status --short && git log --oneline -8`
Expected: working tree limpio (todo commiteado en los 6 commits de las tareas anteriores).
