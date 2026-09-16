# Retrofit dev-standards Backend — Seguimiento — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cerrar los 5 hallazgos de la segunda pasada de auditoría de `dev-standards` en el backend:
agregar cobertura de test a `AuthController` y `BeneficiaryController` (los dos controladores vivos
sin ningún test), corregir el mass assignment potencial en `BeneficiaryController`, extraer la
duplicación byte-a-byte de `checkIdCard()` entre `AffiliateController` y `CounselorController` a un
helper compartido, homologar el patrón `update()` de `DoctorController`/`CounselorController` al ya
usado en `AffiliateController` (`validated()` + `update()` en vez de asignación campo por campo), y
corregir 3 puntos de documentación (variables en español en `DashboardController`, nombres de scopes
desactualizados en `CLAUDE.md`, y dejar constancia de que `AdminController` sigue fuera de alcance).

**Architecture:** Las Tareas 1 y 2 (tests de `AuthController` y `BeneficiaryController`) son
independientes entre sí y van primero — la Tarea 2 crea la red de seguridad que la Tarea 3 necesita
antes de tocar `BeneficiaryController::store()`/`update()`. Las Tareas 4 (extracción de
`IdCardLookup`), 5 y 6 (homologación de `update()` en `Doctor`/`Counselor`) y 7 (documentación) son
mecánicas, de bajo riesgo, y no dependen unas de otras ni de las Tareas 1-3 — pueden ejecutarse en
cualquier orden después de ellas, o en paralelo entre sí.

**Tech Stack:** Laravel 11, PHPUnit (`php artisan test`), SQLite en memoria en tests
(`phpunit.xml`, ya configurado con bloque `<source>` para cobertura), `RefreshDatabase` +
`actingAs()`/`postJson()` para tests Feature (patrón ya establecido en la suite existente).

**Spec:** `docs/superpowers/specs/2026-09-16-retrofit-dev-standards-backend-seguimiento-design.md`

## Global Constraints

- **Idioma — matiz importante frente al spec/plan anterior:** `CLAUDE.md` sección "Reglas Generales"
  punto 1 **ya cambió** desde el plan de referencia del 2026-08-25 (commits `ad67b0e`/`1cc7dfd`,
  confirmados en `git log`): el código en sí (comentarios, PHPDoc, nombres de métodos/propiedades/
  variables) en `app/` debe estar en **inglés**. Los strings de respuesta JSON y mensajes de
  validación que ve el usuario final del panel siguen en **español** (son producto, no código). Se
  verificó leyendo `AuthController.php`, `Md5UserProvider.php`, `DashboardController.php` y
  `User.php` — el 100% de los comentarios ya están en inglés, confirmando que la regla está vigente y
  aplicada. **Todo código nuevo de `app/` en este plan (Form Requests, `IdCardLookup`) usa comentarios
  en inglés; los mensajes JSON (`'message' => '...'`) se mantienen en español, igual que el resto del
  proyecto.**
- **Excepción observada en `tests/`:** los ~15 archivos de test existentes (incluyendo los que
  produjo el plan del 2026-08-25, ya mergeado) usan nombres de método y comentarios en **español** de
  forma uniforme y sin excepciones (`test_store_crea_medico_con_datos_validos`,
  `test_login_exitoso_pone_la_cookie_auth_hint`, etc.). La regla de inglés del punto anterior no se
  ha aplicado a `tests/` en ningún commit. Este plan sigue esa convención observada: los tests nuevos
  usan nombres de método y comentarios en español, para no introducir un archivo inconsistente con
  sus vecinos.
- **No hacer `git commit` dentro de las tareas.** Cada tarea termina en un checkpoint de
  verificación (tests en verde). El usuario decide cuándo integrar (`feedback_no_commits_until_ready`
  en la memoria del proyecto).
- **En Windows, correr los tests con `XDEBUG_MODE=off php artisan test`** — con Xdebug activo, un
  test que fuerza una excepción de red dentro de `Http::fake()` produce un segfault (documentado en
  `CLAUDE.md` sección Testing).
- **Códigos HTTP de validación:** `Affiliate`, `User`, `Doctor`, `Counselor`, `Beneficiary` devuelven
  **400** en validación fallida (`Validator::make()` manual, o Form Request con `failedValidation()`
  sobrescrito). `AuthController::login()` usa `$request->validate()` sin sobrescribir
  `failedValidation()`, así que su fallo de validación (campos `user`/`password` vacíos) responde
  **422** (default de Laravel) — confirmado leyendo el archivo real. Las credenciales inválidas
  responden **422** también, pero por un `return response()->json([...], 422)` explícito, no por el
  validador. No confundir ambos casos con el 400 del resto del proyecto.
- **`AdminController` no se toca ni se testea.** Sigue sin ruta registrada (confirmado:
  `grep AdminController routes/` no devuelve nada), es código huérfano y su disposición (implementar
  vs. eliminar) es una decisión de producto pendiente, fuera de alcance de este plan.
- No se reabre nada de los Hallazgos ya verificados como resueltos en el spec 2026-09-16 (bug de
  `stade`, `BeneficiarySyncService`, rutas de `RenovationController`, Form Requests de
  `Affiliate`/`User`, `WhatsAppClient`, scopes de `Affiliate`).

---

### Task 1: Tests Feature — `AuthController` (login/logout, provider MD5 + bcrypt)

**Files:**
- Test: `tests/Feature/AuthControllerTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- No produce interfaz nueva — es cobertura de un endpoint HTTP existente
  (`POST /login`, `POST /logout` en `routes/web.php`).

**Contexto verificado:**
- `AuthController::login()` (`app/Http/Controllers/AuthController.php:13-42`) valida `user`/
  `password` con `$request->validate()` (422 nativo si faltan), llama
  `Auth::attempt($request->only('user', 'password'))`, responde 422 con
  `{"message": "Credenciales inválidas"}` si falla, y si tiene éxito regenera la sesión y pone la
  cookie `auth_hint`.
- El guard `web` usa el provider `md5-eloquent` (`config/auth.php:62-66`) →
  `App\Auth\Md5UserProvider::validateCredentials()`: si el hash guardado empieza con `$2y$`/`$2a$`
  usa `Hash::check()` (bcrypt), si no compara `md5($password) === $stored`.
- El cast `'password' => 'hashed'` del modelo `User` (`app/Models/User.php:58`) **re-hashea
  automáticamente con bcrypt** cualquier valor asignado vía Eloquent (`create`, `update`,
  asignación de atributo) — así que para simular un password legado en MD5 puro (como quedaron los
  registros migrados del sistema anterior) hay que escribirlo con el query builder
  (`DB::table('users')->update(...)`), no con el modelo, para evitar que el cast lo vuelva a
  hashear.
- La cookie `auth_hint` ya tiene su propia suite (`tests/Feature/AuthHintCookieTest.php`, 3 tests
  verificados leyendo el archivo) — este archivo nuevo **no duplica esos casos**, se enfoca en la
  pieza de mayor riesgo que sí queda sin cubrir: la verificación de credenciales contra ambos
  formatos de hash (`Md5UserProvider`) y el ciclo de sesión.
- Ruta: `Route::post('/login', [AuthController::class, 'login']);` y
  `Route::post('/logout', [AuthController::class, 'logout']);` en `routes/web.php` (fuera de
  `auth:sanctum`, dentro del grupo `web` por defecto de Laravel).

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/AuthControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cubre AuthController::login()/logout() — el único punto de entrada de
 * autenticación del panel, con el provider dual `md5-eloquent`
 * (Md5UserProvider) que acepta tanto passwords MD5 heredadas del sistema
 * anterior como bcrypt ya migradas. La cookie auth_hint tiene su propia
 * suite (AuthHintCookieTest); este archivo no repite esos casos.
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exitoso_con_password_legado_md5(): void
    {
        $user = User::factory()->create();
        // Se escribe con el query builder para evitar que el cast 'hashed'
        // del modelo re-hashee este valor con bcrypt al guardarlo.
        DB::table('users')->where('id', $user->id)->update([
            'password' => md5('claveVieja123'),
        ]);

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'claveVieja123',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_exitoso_con_password_ya_migrada_a_bcrypt(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('claveNueva123'),
        ]);

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'claveNueva123',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_falla_con_password_incorrecta_en_formato_md5(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update([
            'password' => md5('claveCorrecta'),
        ]);

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'claveIncorrecta',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Credenciales inválidas']);
        $this->assertGuest();
    }

    public function test_login_falla_con_usuario_inexistente(): void
    {
        $response = $this->postJson('/login', [
            'user' => 'no-existe',
            'password' => 'lo-que-sea',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Credenciales inválidas']);
        $this->assertGuest();
    }

    public function test_login_requiere_user_y_password(): void
    {
        $response = $this->postJson('/login', []);

        // AuthController::login() usa $request->validate() (default de
        // Laravel), no Validator::make() manual — por eso responde 422 y no
        // el 400 que usan Affiliate/User/Doctor/Counselor/Beneficiary.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user', 'password']);
    }

    public function test_logout_invalida_la_sesion(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/logout');

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Logout OK']);
        $this->assertGuest();
    }
}
```

- [ ] **Step 2: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=AuthControllerTest`
Expected: PASS en los 6 tests.

- [ ] **Step 3: Correr toda la suite para descartar regresiones**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde, incluyendo `AuthHintCookieTest` (mismas rutas, distinto enfoque).

**Checkpoint — no hacer commit.**

---

### Task 2: Tests Feature — `BeneficiaryController`

**Files:**
- Test: `tests/Feature/BeneficiaryControllerTest.php` (crear — no confundir con
  `tests/Feature/BeneficiarySyncServiceTest.php`, que ya existe y cubre la sincronización de
  beneficiarios embebida en `AffiliateController::update()`, un flujo distinto)

**Interfaces:**
- No consume nada de otras tareas.
- Produce la red de seguridad que la Tarea 3 necesita antes de tocar
  `BeneficiaryController::store()`/`update()`.

**Contexto verificado:**
- Ruta: `Route::apiResource('beneficiaries', BeneficiaryController::class);` dentro del grupo
  `auth:sanctum` (`routes/api.php:79`) — requiere `actingAs()` en los tests.
- No existe `BeneficiaryFactory` (confirmado, `database/factories/` no tiene ese archivo) — se crea
  con `Beneficiary::create()` directo.
- `$fillable` de `Beneficiary`: `affiliate_id`, `name`, `id_card`, `bithdate`
  (`app/Models/Beneficiary.php:14-19`).
- Validación real de `store()`: `'affiliate_id' => 'required|exists:affiliates,id'`,
  `'name' => 'required|string|max:255'`, `'id_card' => 'required|string|max:50'`,
  `'bithdate' => 'nullable|date'`. `update()`: las 4 reglas en `nullable`.
- `AffiliateFactory` ya existe y crea también su cadena de `User`/`Counselor`/`Agreement`/`City`
  necesaria — se usa tal cual para satisfacer la FK `affiliate_id`.

- [ ] **Step 1: Escribir los tests**

Crear `tests/Feature/BeneficiaryControllerTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BeneficiaryController tiene ruta viva (apiResource completo,
 * routes/api.php:79) y no tenía ningún test directo. Esta suite también
 * sirve de red de seguridad para el fix de validated() de la Tarea 3 — se
 * escribe antes de tocar el controlador, así que primero fija el
 * comportamiento actual.
 */
class BeneficiaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_beneficiario_con_datos_validos(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/beneficiaries', [
            'affiliate_id' => $affiliate->id,
            'name' => 'Hijo de Prueba',
            'id_card' => '1122334455',
            'bithdate' => '2015-03-10',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('beneficiaries', [
            'affiliate_id' => $affiliate->id,
            'name' => 'Hijo de Prueba',
            'id_card' => '1122334455',
        ]);
    }

    public function test_store_rechaza_affiliate_id_inexistente(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/beneficiaries', [
            'affiliate_id' => 999999,
            'name' => 'Sin Afiliado',
            'id_card' => '9988776655',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['affiliate_id']);
    }

    public function test_store_rechaza_name_vacio(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/beneficiaries', [
            'affiliate_id' => $affiliate->id,
            'id_card' => '1231231231',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_index_lista_beneficiarios(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'Beneficiario Uno',
            'id_card' => '1112223334',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/beneficiaries');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_retorna_el_beneficiario(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $beneficiary = Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'Beneficiario Dos',
            'id_card' => '2223334445',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/beneficiaries/{$beneficiary->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Beneficiario Dos']);
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/beneficiaries/999999');

        $response->assertStatus(404);
    }

    public function test_update_permite_edicion_parcial(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $beneficiary = Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'Nombre Original',
            'id_card' => '3334445556',
        ]);

        $response = $this->actingAs($admin)->patchJson("/api/beneficiaries/{$beneficiary->id}", [
            'name' => 'Nombre Editado',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('beneficiaries', [
            'id' => $beneficiary->id,
            'name' => 'Nombre Editado',
            'id_card' => '3334445556',
        ]);
    }

    public function test_update_retorna_404_si_no_existe(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->patchJson('/api/beneficiaries/999999', [
            'name' => 'No Importa',
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_elimina_el_beneficiario(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $beneficiary = Beneficiary::create([
            'affiliate_id' => $affiliate->id,
            'name' => 'A Eliminar',
            'id_card' => '4445556667',
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/beneficiaries/{$beneficiary->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('beneficiaries', ['id' => $beneficiary->id]);
    }
}
```

- [ ] **Step 2: Correr los tests**

Run: `XDEBUG_MODE=off php artisan test --filter=BeneficiaryControllerTest`
Expected: PASS en los 9 tests (fija el comportamiento actual, antes del fix de la Tarea 3).

- [ ] **Step 3: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Task 3: Fix `BeneficiaryController` — usar `validated()` en vez de `$request->all()`

**Files:**
- Modify: `app/Http/Controllers/BeneficiaryController.php` (`store()`, `update()`)
- Modify: `app/Models/Beneficiary.php` (quitar comentario banner)

**Interfaces:**
- Depende de la Tarea 2 (tests ya existen y deben seguir en verde después de este cambio —
  `$fillable` coincide hoy con las reglas de validación, así que el comportamiento observable no
  cambia).
- No produce interfaz nueva.

**Contexto verificado:** `store()` corre `Validator::make($request->all(), [...])` pero persiste con
`Beneficiary::create($request->all())` (línea 52) — ignora el resultado validado. `update()` hace lo
mismo en la línea 106. Hoy no es explotable (`$fillable` = exactamente los 4 campos validados), pero
es el único controlador del proyecto que no sigue el patrón ya migrado en `Affiliate`/`User`
(`$validator->validated()`); si se agrega una columna a `$fillable` sin agregar también su regla,
este controlador empezaría a aceptar el campo sin validar, silenciosamente. Además, `Beneficiary.php`
líneas 21-22 tienen un comentario banner (`// Relaciones` / `// El beneficiario pertenece a un
afiliado`) que no aporta ningún WHY — el método `affiliate()` ya es autoexplicativo por nombre y tipo
de retorno.

- [ ] **Step 1: Confirmar que los tests de la Tarea 2 pasan antes del cambio (baseline)**

Run: `XDEBUG_MODE=off php artisan test --filter=BeneficiaryControllerTest`
Expected: PASS (ya lo estaba desde la Tarea 2 — este paso solo confirma el punto de partida antes de
tocar el controlador).

- [ ] **Step 2: Aplicar el fix en `store()`**

En `app/Http/Controllers/BeneficiaryController.php`, reemplazar:
```php
        $beneficiary = Beneficiary::create($request->all());
```
por:
```php
        $beneficiary = Beneficiary::create($validator->validated());
```

- [ ] **Step 3: Aplicar el fix en `update()`**

Reemplazar:
```php
        $beneficiary->update($request->all());
```
por:
```php
        $beneficiary->update($validator->validated());
```

- [ ] **Step 4: Quitar el comentario banner en el modelo**

En `app/Models/Beneficiary.php`, reemplazar:
```php
    // Relaciones
    // El beneficiario pertenece a un afiliado
    public function affiliate()
```
por:
```php
    public function affiliate()
```

- [ ] **Step 5: Correr los tests y confirmar que siguen en verde**

Run: `XDEBUG_MODE=off php artisan test --filter=BeneficiaryControllerTest`
Expected: PASS en los 9 tests — mismo comportamiento observable, ahora persistiendo solo el array
validado.

- [ ] **Step 6: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde, incluyendo `BeneficiarySyncServiceTest` (usa `AffiliateController`, no este
controlador, pero comparte el modelo `Beneficiary`).

**Checkpoint — no hacer commit.**

---

### Task 4: Extraer `checkIdCard()` duplicado a `App\Support\IdCardLookup`

**Files:**
- Create: `app/Support/IdCardLookup.php`
- Modify: `app/Http/Controllers/AffiliateController.php` (método `checkIdCard()`)
- Modify: `app/Http/Controllers/CounselorController.php` (método `checkIdCard()`)
- Test: `tests/Feature/Support/IdCardLookupTest.php` (crear)
- Test: `tests/Feature/AffiliateControllerTest.php` (agregar casos de `checkIdCard`, hoy sin
  cobertura directa)
- Test: `tests/Feature/CounselorControllerTest.php` (agregar caso de `checkIdCard` con aserciones
  explícitas — el test existente `test_active_counselors_y_check_id_card` solo verifica el status
  200, no el valor de `exists`)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: `IdCardLookup::normalize(string $idCard): string` e
  `IdCardLookup::exists(string $modelClass, string $idCard, ?int $ignoreId = null): bool` — el
  parámetro `$idCard` de `exists()` espera el valor **ya normalizado** (solo dígitos); no vuelve a
  normalizar internamente, para que `normalize()` sea la única fuente de esa lógica.

**Contexto verificado:** `AffiliateController::checkIdCard()`
(`app/Http/Controllers/AffiliateController.php:192-216`) y
`CounselorController::checkIdCard()` (`app/Http/Controllers/CounselorController.php:228-252`) son
idénticos salvo el modelo (`Affiliate` vs `Counselor`) y los 2 mensajes de texto ("Documento de
identidad vacío"/"El documento de identidad ya existe" vs "Cédula vacía"/"La cédula ya existe") — el
mensaje es legítimamente distinto por dominio (spec 2026-09-16, Hallazgo 1), así que no se comparte.
Ambos métodos: normalizan `id_card` con `preg_replace('/\D/', '', ...)`, si queda vacío responden
`{exists: false, message: "..."}` sin tocar la base de datos, si no consultan `exists()` sobre su
modelo excluyendo opcionalmente `ignore_id`.

Se exponen dos métodos separados (`normalize()` + `exists()`) en vez de uno solo, porque el
controlador necesita el valor ya normalizado para decidir si mostrar el mensaje de "vacío" — que es
el único texto realmente distinto por dominio — antes de decidir si consulta la base de datos.

- [ ] **Step 1: Escribir el test del helper**

Crear `tests/Feature/Support/IdCardLookupTest.php`:
```php
<?php

namespace Tests\Feature\Support;

use App\Models\Affiliate;
use App\Support\IdCardLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdCardLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_quita_todo_lo_que_no_sea_digito(): void
    {
        $this->assertSame('123456789', IdCardLookup::normalize('1.234-56.789'));
        $this->assertSame('', IdCardLookup::normalize('   '));
    }

    public function test_exists_retorna_true_si_hay_coincidencia(): void
    {
        Affiliate::factory()->create(['id_card' => '555666777']);

        $this->assertTrue(IdCardLookup::exists(Affiliate::class, '555666777'));
    }

    public function test_exists_retorna_false_si_no_hay_coincidencia(): void
    {
        $this->assertFalse(IdCardLookup::exists(Affiliate::class, '999999999'));
    }

    public function test_exists_excluye_el_ignore_id(): void
    {
        $affiliate = Affiliate::factory()->create(['id_card' => '444555666']);

        $this->assertFalse(IdCardLookup::exists(Affiliate::class, '444555666', $affiliate->id));
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla (la clase todavía no existe)**

Run: `XDEBUG_MODE=off php artisan test --filter=IdCardLookupTest`
Expected: ERROR (`Class "App\Support\IdCardLookup" not found`).

- [ ] **Step 3: Crear el helper**

Crear `app/Support/IdCardLookup.php`:
```php
<?php

declare(strict_types=1);

namespace App\Support;

class IdCardLookup
{
    /**
     * Strips every non-digit character. Shared by every checkIdCard()
     * endpoint so the normalization rule lives in exactly one place.
     */
    public static function normalize(string $idCard): string
    {
        return preg_replace('/\D/', '', $idCard) ?? '';
    }

    /**
     * Checks whether a record with this (already normalized) id card exists
     * for the given model, optionally excluding one id — used when editing,
     * so the record doesn't collide with itself.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    public static function exists(string $modelClass, string $idCard, ?int $ignoreId = null): bool
    {
        $query = $modelClass::query()->where('id_card', $idCard);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
```

- [ ] **Step 4: Correr el test del helper y confirmar que pasa**

Run: `XDEBUG_MODE=off php artisan test --filter=IdCardLookupTest`
Expected: PASS en los 4 tests.

- [ ] **Step 5: Migrar `AffiliateController::checkIdCard()`**

Agregar el import junto a los demás `use` de `app/Http/Controllers/AffiliateController.php`:
```php
use App\Support\IdCardLookup;
```
Reemplazar el cuerpo del método (líneas 192-216):
```php
    public function checkIdCard(Request $request)
    {
        $idCard = preg_replace('/\D/', '', (string) $request->query('id_card', ''));
        $ignoreId = $request->query('ignore_id');

        if ($idCard === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Documento de identidad vacío',
            ], 200);
        }

        $q = Affiliate::query()->where('id_card', $idCard);

        if ($ignoreId) {
            $q->where('id', '!=', (int) $ignoreId);
        }

        $exists = $q->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El documento de identidad ya existe' : 'Disponible',
        ], 200);
    }
```
por:
```php
    public function checkIdCard(Request $request)
    {
        $idCard = IdCardLookup::normalize((string) $request->query('id_card', ''));
        $ignoreId = $request->query('ignore_id');

        if ($idCard === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Documento de identidad vacío',
            ], 200);
        }

        $exists = IdCardLookup::exists(Affiliate::class, $idCard, $ignoreId ? (int) $ignoreId : null);

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El documento de identidad ya existe' : 'Disponible',
        ], 200);
    }
```

- [ ] **Step 6: Migrar `CounselorController::checkIdCard()`**

Agregar el import junto a los demás `use` de `app/Http/Controllers/CounselorController.php`:
```php
use App\Support\IdCardLookup;
```
Reemplazar el cuerpo del método (líneas 228-252) por:
```php
    public function checkIdCard(Request $request)
    {
        $idCard = IdCardLookup::normalize((string) $request->query('id_card', ''));
        $ignoreId = $request->query('ignore_id'); // optional (for editing)

        if ($idCard === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Cédula vacía',
            ], 200);
        }

        $exists = IdCardLookup::exists(Counselor::class, $idCard, $ignoreId ? (int) $ignoreId : null);

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'La cédula ya existe' : 'Disponible',
        ], 200);
    }
```

- [ ] **Step 7: Agregar cobertura HTTP directa de `checkIdCard` en `AffiliateController` (no existía)**

Agregar al final de la clase en `tests/Feature/AffiliateControllerTest.php` (antes del `}` de
cierre):
```php
    public function test_check_id_card_detecta_duplicado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['id_card' => '777888999']);

        $response = $this->actingAs($admin)->getJson('/api/affiliates/check-id-card?id_card=777888999');

        $response->assertStatus(200);
        $response->assertJson(['exists' => true]);
    }

    public function test_check_id_card_ignora_el_propio_registro_con_ignore_id(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['id_card' => '666777888']);

        $response = $this->actingAs($admin)->getJson(
            "/api/affiliates/check-id-card?id_card=666777888&ignore_id={$affiliate->id}"
        );

        $response->assertStatus(200);
        $response->assertJson(['exists' => false]);
    }

    public function test_check_id_card_vacio_no_consulta_la_base_de_datos(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/affiliates/check-id-card?id_card=');

        $response->assertStatus(200);
        $response->assertJson(['exists' => false, 'message' => 'Documento de identidad vacío']);
    }
```

- [ ] **Step 8: Reforzar la cobertura de `checkIdCard` en `CounselorController`**

El test `test_active_counselors_y_check_id_card` ya existente solo verifica el status 200. Agregar
al final de la clase en `tests/Feature/CounselorControllerTest.php` (antes del `}` de cierre) un
caso con aserciones explícitas sobre `exists`:
```php
    public function test_check_id_card_detecta_duplicado_y_respeta_ignore_id(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');
        $idCard = $created->json('data.id_card');

        $duplicate = $this->actingAs($admin)->getJson("/api/counselors/check-id-card?id_card={$idCard}");
        $duplicate->assertStatus(200);
        $duplicate->assertJson(['exists' => true]);

        $ignored = $this->actingAs($admin)->getJson(
            "/api/counselors/check-id-card?id_card={$idCard}&ignore_id={$id}"
        );
        $ignored->assertStatus(200);
        $ignored->assertJson(['exists' => false]);
    }
```

- [ ] **Step 9: Correr los tests afectados**

Run: `XDEBUG_MODE=off php artisan test --filter=IdCardLookupTest`
Run: `XDEBUG_MODE=off php artisan test --filter=AffiliateControllerTest`
Run: `XDEBUG_MODE=off php artisan test --filter=CounselorControllerTest`
Expected: PASS en los 3 archivos.

- [ ] **Step 10: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Task 5: Homologar `DoctorController::update()` al patrón `validated()` + `update()`

**Files:**
- Create: `app/Http/Requests/UpdateDoctorRequest.php`
- Modify: `app/Http/Controllers/DoctorController.php` (método `update()`)
- Test: `tests/Feature/DoctorControllerTest.php` (agregar casos)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: `UpdateDoctorRequest` — mismo patrón exacto de `UpdateAffiliateRequest`
  (`authorize(): true`, `failedValidation()` devolviendo 400).

**Contexto verificado:** `DoctorController::update()`
(`app/Http/Controllers/DoctorController.php:220-271`) valida con `Validator::make($request->all(),
[...])` (todas las reglas en `nullable`) y luego persiste campo por campo con
`if ($request->filled('x')) $doctor->x = $request->x;` para cada uno de los 10 campos, en vez de
`$doctor->update($validator->validated())` como ya hace `AffiliateController::update()`.

Columnas NOT NULL de `doctors` (migración `2025_09_11_042140_create_doctors_table.php`):
`specialty_id`, `name`, `lastname`, `phone`, `movil`, `address`, `secretary_name`,
`value_agreement`, `city_id`, más `state` (tinyInteger con default 1 pero sin `->nullable()`).
`email` es nullable (agregado en `2026_04_24_062302_add_email_to_doctors_table.php`).

**Cambio de comportamiento deliberado:** para poder reemplazar la asignación campo-por-campo por
`$doctor->update($request->validated())` de forma segura, las reglas de las columnas NOT NULL pasan
de `nullable` a `sometimes|required` — el mismo patrón que ya usa `UpdateAffiliateRequest` para
`movil`/`name`/`lastname`/`id_card`/`validity_end` (ver `CLAUDE.md`, sección Afiliados). Con
`nullable` puro, enviar `"name": ""` en una edición parcial hoy no hace nada (el `filled()` lo
ignora); con `sometimes|required` pasando a `validated()`, el mismo request devuelve 400 en vez de
ignorar silenciosamente el campo vacío — es un endurecimiento intencional, no un bug, y se cubre con
un test nuevo.

- [ ] **Step 1: Escribir los tests que fijan el comportamiento nuevo (antes del fix)**

Agregar al final de la clase en `tests/Feature/DoctorControllerTest.php` (antes del `}` de cierre):
```php
    public function test_update_rechaza_name_vacio_enviado_explicitamente(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'name' => '',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_rechaza_movil_invalido(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'movil' => '123',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['movil']);
    }

    public function test_update_permite_limpiar_email_enviando_null(): void
    {
        $admin = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/doctors/{$doctor->id}", [
            'email' => null,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id, 'email' => null]);
    }
```

- [ ] **Step 2: Correr los tests nuevos y confirmar que fallan contra el código actual**

Run: `XDEBUG_MODE=off php artisan test --filter=DoctorControllerTest`
Expected: FAIL en `test_update_rechaza_name_vacio_enviado_explicitamente` (hoy responde 200 e
ignora el campo vacío en vez de rechazarlo). Los otros 2 deberían pasar ya (comportamiento sin
cambios).

- [ ] **Step 3: Crear `UpdateDoctorRequest`**

Crear `app/Http/Requests/UpdateDoctorRequest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateDoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => 'sometimes|required|string|max:255',
            'lastname'        => 'sometimes|required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'specialty_id'    => 'sometimes|required|exists:specialties,id',
            'city_id'         => 'sometimes|required|exists:cities,id',
            'phone'           => 'sometimes|required|string|max:255',
            'movil'           => 'sometimes|required|digits:10',
            'address'         => 'sometimes|required|string|max:255',
            'secretary_name'  => 'sometimes|required|string|max:255',
            'value_agreement' => 'sometimes|required|numeric|min:10000',
            'state'           => 'sometimes|required|in:1,2',
        ];
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

- [ ] **Step 4: Migrar `DoctorController::update()`**

Agregar el import junto a los demás `use` de `app/Http/Controllers/DoctorController.php`:
```php
use App\Http\Requests\UpdateDoctorRequest;
```
Reemplazar el método completo (líneas 220-271):
```php
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'            => 'nullable|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'specialty_id'    => 'nullable|exists:specialties,id',
            'city_id'         => 'nullable|exists:cities,id',
            'phone'           => 'nullable|string|max:255',
            'movil'           => 'nullable|digits:10',
            'address'         => 'nullable|string|max:255',
            'secretary_name'  => 'nullable|string|max:255',
            'value_agreement' => 'nullable|numeric|min:10000',
            'state'           => 'nullable|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('name')) $doctor->name = $request->name;
        if ($request->filled('lastname')) $doctor->lastname = $request->lastname;
        if ($request->has('email')) $doctor->email = $request->email;
        if ($request->filled('specialty_id')) $doctor->specialty_id = $request->specialty_id;
        if ($request->filled('city_id')) $doctor->city_id = $request->city_id;
        
        if ($request->has('phone')) $doctor->phone = $request->phone;
        if ($request->has('movil')) $doctor->movil = $request->movil;
        if ($request->has('address')) $doctor->address = $request->address;
        if ($request->has('secretary_name')) $doctor->secretary_name = $request->secretary_name;
        
        if ($request->filled('value_agreement')) $doctor->value_agreement = $request->value_agreement;
        if ($request->filled('state')) $doctor->state = $request->state;

        $doctor->save();

        return response()->json([
            'message' => 'Médico actualizado correctamente',
            'data' => $doctor,
        ], 200);
    }
```
por:
```php
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDoctorRequest $request, $id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado',
            ], 404);
        }

        $doctor->update($request->validated());

        return response()->json([
            'message' => 'Médico actualizado correctamente',
            'data' => $doctor,
        ], 200);
    }
```
Si tras este cambio `Validator` (el facade `Illuminate\Support\Facades\Validator`) queda sin usos en
el archivo (sigue usándose en `store()`), no quitar el `use` — confirmar leyendo el resto del
archivo antes de tocar los imports.

- [ ] **Step 5: Correr los tests y confirmar que pasan**

Run: `XDEBUG_MODE=off php artisan test --filter=DoctorControllerTest`
Expected: PASS en los 10 tests (los 7 originales + los 3 nuevos de este task).

- [ ] **Step 6: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Task 6: Homologar `CounselorController::update()` al patrón `validated()` + `update()`

**Files:**
- Create: `app/Http/Requests/UpdateCounselorRequest.php`
- Modify: `app/Http/Controllers/CounselorController.php` (método `update()`, y visibilidad de
  `typeContraValues()`)
- Test: `tests/Feature/CounselorControllerTest.php` (agregar casos)

**Interfaces:**
- No consume nada de otras tareas (independiente de la Tarea 5, aunque sigue el mismo patrón).
- Produce: `UpdateCounselorRequest`, y `CounselorController::typeContraValues()` pasa de `private` a
  `public static` para que el Form Request pueda reusar la misma lista de valores válidos de
  `type_contra` sin duplicarla.

**Contexto verificado:** `CounselorController::update()`
(`app/Http/Controllers/CounselorController.php:134-206`) tiene el mismo patrón de asignación campo
por campo que `DoctorController::update()`. Columnas NOT NULL de `counselors` (migración
`2025_09_09_054148_create_counselors_table.php`): `name`, `lastname`, `id_card`, `type_contra`,
`password`, `city_id`, `user_id`, más `state` (mismo caso que `doctors`, sin `->nullable()`).
Nullable: `address`, `date_admission`, `email`, `rol`, `phone`, `movil`.

**Dos particularidades que el reemplazo directo por `validated()` + `update()` rompería si no se
manejan aparte:**
1. **`password`:** `Counselor` (a diferencia de `User`) **no** tiene el cast `'password' =>
   'hashed'` (confirmado en `app/Models/Counselor.php` — no hay método `casts()`). El código actual
   hashea manualmente con `Hash::make()` solo si `filled('password')`. Pasar `validated()` tal cual
   a `update()` guardaría la contraseña en texto plano si se envía — hay que seguir tratándola
   aparte.
2. **`email`:** el código actual usa `has('email')` (no `filled()`) explícitamente para permitir
   limpiar el email a `null` — comentario en el código: "to allow clearing the email... must be
   handled with has()". Con una regla `nullable` (sin `sometimes`), `validated()` ya replica este
   comportamiento de forma natural (si la clave viene en el request, aunque sea `null`, queda en el
   array validado; si no viene, se excluye) — no hace falta tratarla aparte, pero si el test de
   "limpiar email" falla, es la primera línea a revisar.

**Cambio de comportamiento deliberado (igual que en la Tarea 5):** las columnas NOT NULL pasan de
`nullable` a `sometimes|required` en las reglas de `update()` — enviar `"name": ""` ahora responde
400 en vez de ignorar el campo silenciosamente.

- [ ] **Step 1: Escribir los tests que fijan el comportamiento nuevo (antes del fix)**

Agregar al final de la clase en `tests/Feature/CounselorControllerTest.php` (antes del `}` de
cierre):
```php
    public function test_update_rechaza_name_vacio_enviado_explicitamente(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", [
            'name' => '',
        ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_permite_limpiar_email_enviando_null(): void
    {
        $admin = User::factory()->create();
        $payload = $this->payloadValido();
        $payload['email'] = 'inicial@example.com';
        $created = $this->actingAs($admin)->postJson('/api/counselors', $payload);
        $id = $created->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", [
            'email' => null,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('counselors', ['id' => $id, 'email' => null]);
    }

    public function test_update_hashea_la_nueva_password(): void
    {
        $admin = User::factory()->create();
        $created = $this->actingAs($admin)->postJson('/api/counselors', $this->payloadValido());
        $id = $created->json('data.id');

        $response = $this->actingAs($admin)->patchJson("/api/counselors/{$id}", [
            'password' => 'nuevaClave123',
        ]);

        $response->assertStatus(200);
        $stored = \App\Models\Counselor::find($id)->password;
        $this->assertNotSame('nuevaClave123', $stored);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('nuevaClave123', $stored));
    }
```

- [ ] **Step 2: Correr los tests nuevos y confirmar que `test_update_rechaza_name_vacio...` falla**

Run: `XDEBUG_MODE=off php artisan test --filter=CounselorControllerTest`
Expected: FAIL en `test_update_rechaza_name_vacio_enviado_explicitamente` contra el código actual
(hoy ignora el campo vacío en vez de rechazarlo). Los otros 2 deberían pasar ya.

- [ ] **Step 3: Hacer pública y estática `typeContraValues()`**

En `app/Http/Controllers/CounselorController.php`, reemplazar:
```php
    private function typeContraValues(): array
    {
        return [
            'Término Fijo',
            'Término Indefinido',
            'Corretaje',
            'Con Garantizado',
        ];
    }
```
por:
```php
    // Public and static so UpdateCounselorRequest can reuse the same list
    // instead of duplicating the 4 valid values for `type_contra`.
    public static function typeContraValues(): array
    {
        return [
            'Término Fijo',
            'Término Indefinido',
            'Corretaje',
            'Con Garantizado',
        ];
    }
```
El único caller existente, `$this->typeContraValues()` dentro de `store()`, sigue funcionando sin
cambios (PHP permite llamar un método estático a través de `$this->`).

- [ ] **Step 4: Crear `UpdateCounselorRequest`**

Crear `app/Http/Requests/UpdateCounselorRequest.php`:
```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Controllers\CounselorController;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCounselorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Route::apiResource('counselors', ...) exposes the id as {counselor};
        // used to exclude the record itself from the unique rules, same as
        // the original Validator::make() ("unique:counselors,id_card," . $id).
        $id = $this->route('counselor');

        return [
            'name'           => 'sometimes|required|string|max:255',
            'lastname'       => 'sometimes|required|string|max:255',
            'id_card'        => 'sometimes|required|regex:/^\d+$/|max:100|unique:counselors,id_card,' . $id,
            'address'        => 'nullable|string|max:255',
            'date_admission' => 'nullable|date',
            'type_contra'    => 'sometimes|required|in:' . implode(',', CounselorController::typeContraValues()),
            'email'          => 'nullable|email|max:255|unique:counselors,email,' . $id,
            'password'       => 'nullable|string|min:6',
            'rol'            => 'nullable|numeric',
            'phone'          => 'nullable|string|max:255',
            'movil'          => 'nullable|digits:10',
            'state'          => 'sometimes|required|in:1,2',
            'city_id'        => 'sometimes|required|exists:cities,id',
            'user_id'        => 'sometimes|required|exists:users,id',
        ];
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

- [ ] **Step 5: Migrar `CounselorController::update()`**

Agregar el import junto a los demás `use` de `app/Http/Controllers/CounselorController.php`:
```php
use App\Http\Requests\UpdateCounselorRequest;
```
Reemplazar el método completo (líneas 134-206):
```php
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $counselor = Counselor::find($id);

        if (!$counselor) {
            return response()->json([
                'message' => 'Vendedor no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'nullable|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            'id_card'        => 'nullable|regex:/^\d+$/|max:100|unique:counselors,id_card,' . $id,
            'address'        => 'nullable|string|max:255',
            'date_admission' => 'nullable|date',

            'type_contra'    => 'nullable|in:' . implode(',', $this->typeContraValues()),

            // nullable + unique: only validates uniqueness if a value is sent
            'email'          => 'nullable|email|max:255|unique:counselors,email,' . $id,
            'password'       => 'nullable|string|min:6',

            'rol'            => 'nullable|numeric',
            'phone'          => 'nullable|string|max:255',
            'movil'          => 'nullable|digits:10',

            'state'          => 'nullable|in:1,2',

            'city_id'        => 'nullable|exists:cities,id',
            'user_id'        => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('name')) $counselor->name = $request->name;
        if ($request->filled('lastname')) $counselor->lastname = $request->lastname;
        if ($request->filled('id_card')) $counselor->id_card = $request->id_card;
        if ($request->filled('address')) $counselor->address = $request->address;
        if ($request->filled('date_admission')) $counselor->date_admission = $request->date_admission;

        if ($request->filled('type_contra')) $counselor->type_contra = $request->type_contra;

        // NOTE: to allow clearing the email (setting it to null), it must be handled with has()
        if ($request->has('email')) {
            $counselor->email = $request->email; // can be null
        }

        if ($request->filled('password')) {
            $counselor->password = Hash::make($request->password);
        }

        if ($request->filled('rol')) $counselor->rol = $request->rol;
        if ($request->filled('phone')) $counselor->phone = $request->phone;
        if ($request->filled('movil')) $counselor->movil = $request->movil;

        if ($request->filled('state')) $counselor->state = $request->state;

        if ($request->filled('city_id')) $counselor->city_id = $request->city_id;
        if ($request->filled('user_id')) $counselor->user_id = $request->user_id;

        $counselor->save();

        return response()->json([
            'message' => 'Vendedor actualizado correctamente',
            'data' => $counselor,
        ], 200);
    }
```
por:
```php
    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCounselorRequest $request, $id)
    {
        $counselor = Counselor::find($id);

        if (!$counselor) {
            return response()->json([
                'message' => 'Vendedor no encontrado',
            ], 404);
        }

        $data = $request->validated();

        // Counselor has no 'hashed' cast (unlike User), so password must be
        // hashed explicitly here — persisting validated() as-is would store
        // the plaintext value. If it wasn't sent (or was sent as null), it's
        // dropped so the existing hash on the row is left untouched.
        if (array_key_exists('password', $data) && $data['password'] !== null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $counselor->update($data);

        return response()->json([
            'message' => 'Vendedor actualizado correctamente',
            'data' => $counselor,
        ], 200);
    }
```

- [ ] **Step 6: Correr los tests y confirmar que pasan**

Run: `XDEBUG_MODE=off php artisan test --filter=CounselorControllerTest`
Expected: PASS en los 8 tests (los 5 originales + los 3 nuevos de este task).

- [ ] **Step 7: Correr toda la suite**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Task 7: Documentación — variables en `DashboardController`, scopes en `CLAUDE.md`, nota sobre `AdminController`

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php` (líneas 101-116)
- Modify: `CLAUDE.md` (sección "Scopes de vigencia en `Affiliate`")

**Interfaces:** ninguna — son cambios de nombres de variables locales y de documentación, sin efecto
observable.

**Contexto verificado:**
- `DashboardController::charts()` (`app/Http/Controllers/DashboardController.php:101-116`) tiene la
  closure `$mesesPorFranquicia` con parámetro `$totalsPorUsuario`, en español, fuera de los commits
  que migraron el resto del código base a inglés (regla vigente en `CLAUDE.md`, ver Global
  Constraints de este plan).
- `CLAUDE.md` sección "Scopes de vigencia en `Affiliate`" documenta `scopeActivosVencidos()`,
  `scopeActivosVencenHoy()`, `scopeInactivosPorVencimiento()`. El código real
  (`app/Models/Affiliate.php:93,100,107`) usa `scopeActiveExpired()`, `scopeActiveExpiringToday()`,
  `scopeInactiveByExpiry()` — la migración a inglés renombró los scopes pero no se actualizó la
  documentación.
- `AdminController` (Hallazgo 4.1 del spec) **no se toca** — sigue sin ruta registrada
  (confirmado: `grep AdminController routes/` no devuelve nada). No hay archivo de código que
  modificar para este punto; queda documentado aquí, en el plan, que sigue pendiente de decisión de
  producto (implementar la ruta o eliminar el controlador) antes de invertir en documentarlo.

- [ ] **Step 1: Renombrar las variables en `DashboardController`**

En `app/Http/Controllers/DashboardController.php`, reemplazar:
```php
            $mesesPorFranquicia = function ($totalsPorUsuario, $franchiseId) {
                $months = array_fill(0, 12, 0);
                foreach ($totalsPorUsuario->get($franchiseId, []) as $row) {
                    $months[$row->mes - 1] = (int) $row->total;
                }
                return $months;
            };

            $data['by_franchise'] = [
                'users' => $franchises->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'appointments_by_franchise' => $franchises
                    ->map(fn ($f) => $mesesPorFranquicia($apptTotals, $f->id))
                    ->values(),
                'affiliates_by_franchise' => $franchises
                    ->map(fn ($f) => $mesesPorFranquicia($affilTotals, $f->id))
                    ->values(),
            ];
```
por:
```php
            $monthsByFranchise = function ($totalsByUser, $franchiseId) {
                $months = array_fill(0, 12, 0);
                foreach ($totalsByUser->get($franchiseId, []) as $row) {
                    $months[$row->mes - 1] = (int) $row->total;
                }
                return $months;
            };

            $data['by_franchise'] = [
                'users' => $franchises->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'appointments_by_franchise' => $franchises
                    ->map(fn ($f) => $monthsByFranchise($apptTotals, $f->id))
                    ->values(),
                'affiliates_by_franchise' => $franchises
                    ->map(fn ($f) => $monthsByFranchise($affilTotals, $f->id))
                    ->values(),
            ];
```

- [ ] **Step 2: Correr los tests de `DashboardController` para confirmar que no hay cambio de
  comportamiento**

Run: `XDEBUG_MODE=off php artisan test --filter=DashboardChartsTest`
Run: `XDEBUG_MODE=off php artisan test --filter=DashboardStatsTest`
Expected: PASS en ambos — solo cambian nombres de variables locales, no la lógica.

- [ ] **Step 3: Corregir los nombres de los scopes en `CLAUDE.md`**

En `CLAUDE.md`, dentro de la sección `### Scopes de vigencia en \`Affiliate\``, reemplazar:
```markdown
- **`scopeActivosVencidos()`** — `stade = 1` y `validity_end < hoy`. Usado por el comando `affiliates:update-expired`.
- **`scopeActivosVencenHoy()`** — `stade = 1` y `validity_end = hoy`. Usado por `AffiliateController::expiringToday()`.
- **`scopeInactivosPorVencimiento()`** — `stade = 2` y `validity_end < hoy`. Usado por `DashboardController::stats()` (métrica `inactive_by_expiry`).
```
por:
```markdown
- **`scopeActiveExpired()`** — `stade = 1` y `validity_end < hoy`. Usado por el comando `affiliates:update-expired`.
- **`scopeActiveExpiringToday()`** — `stade = 1` y `validity_end = hoy`. Usado por `AffiliateController::expiringToday()`.
- **`scopeInactiveByExpiry()`** — `stade = 2` y `validity_end < hoy`. Usado por `DashboardController::stats()` (métrica `inactive_by_expiry`).
```

- [ ] **Step 4: Correr toda la suite (verificación de no-regresión, aunque este paso solo tocó
  Markdown y nombres de variables locales)**

Run: `XDEBUG_MODE=off php artisan test`
Expected: todo en verde.

**Checkpoint — no hacer commit.**

---

### Task 8: Verificación final integrada

**Files:** ninguno.

**Interfaces:** consume el resultado combinado de las Tareas 1-7.

- [ ] **Step 1: Suite completa en verde**

Run: `XDEBUG_MODE=off php artisan test`
Expected: cero fallos, incluyendo todos los tests nuevos de este plan más los de los planes
anteriores (2026-08-07, 2026-08-25).

- [ ] **Step 2: Confirmar que `AdminController` sigue sin ruta (no se tocó por error)**

Run: `grep -r "AdminController" routes/`
Expected: sin resultados — confirma que este plan no introdujo accidentalmente una ruta para él.

- [ ] **Step 3: Revisar el diff completo antes de decidir integrar**

```bash
git diff --stat
git diff
```
Expected: cambios concentrados en los archivos de este plan (`AuthController` no se tocó, solo se
le agregaron tests; `BeneficiaryController`, `Beneficiary.php`, `AffiliateController.php`,
`CounselorController.php`, `DoctorController.php`, `DashboardController.php`, `CLAUDE.md`, y los
`app/Http/Requests/*` y `app/Support/*` nuevos) — sin modificaciones accidentales a
`config/auth.php`, `config/cors.php`, ni a nada ya resuelto en sesiones anteriores.

**Checkpoint final — el usuario decide cuándo y cómo commitear/pushear este conjunto de cambios.**
