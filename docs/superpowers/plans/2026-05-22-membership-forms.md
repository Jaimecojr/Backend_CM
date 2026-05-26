# Módulo Solicitudes de Afiliación — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Conectar el formulario público de afiliación con un módulo admin que permite convertir solicitudes en afiliados reutilizando el formulario existente.

**Architecture:** El formulario público envía datos a `POST /api/public/affiliate-request` que crea un `MembershipForm` con `state=0`. El panel admin lista estas solicitudes; al hacer clic en "Crear afiliado" navega a `/4dnn1n/affiliates/new?from={id}`, la página pre-carga los datos de la solicitud y al guardar exitosamente marca la solicitud como `state=1` y redirige a `/4dnn1n/membership-forms`.

**Tech Stack:** Laravel 11 (PHP), PHPUnit, Next.js 14 App Router, TypeScript, TanStack Table, Tailwind CSS.

---

## Mapa de archivos

### Backend (`api-cm`)
| Archivo | Acción |
|---|---|
| `app/Models/MembershipForm.php` | Modificar: corregir `$fillable` |
| `database/factories/MembershipFormFactory.php` | Crear: factory para tests |
| `app/Http/Controllers/MembershipFormController.php` | Modificar: implementar todos los métodos |
| `routes/api.php` | Modificar: registrar rutas públicas y protegidas |
| `tests/Feature/MembershipFormPublicTest.php` | Crear: tests del endpoint público |
| `tests/Feature/MembershipFormAdminTest.php` | Crear: tests de los endpoints protegidos |

### Frontend (`frontend-cm`)
| Archivo | Acción |
|---|---|
| `src/app/4dnn1n/membership-forms/fetch.ts` | Crear: tipos y funciones de API |
| `src/app/4dnn1n/membership-forms/_components/columns.tsx` | Crear: columnas de la tabla |
| `src/app/4dnn1n/membership-forms/page.tsx` | Crear: página principal del módulo |
| `src/components/Layouts/sidebar/data/index.ts` | Modificar: agregar URL a "Afiliaciones" |
| `src/app/4dnn1n/affiliates/new/page.tsx` | Modificar: leer `?from`, pre-cargar datos, redirigir post-guardado |

---

## Nota sobre el formulario público

El formulario `afiliarse/page.tsx` envía estos nombres de campo (distintos a los de la BD):

| Campo enviado | Campo en BD |
|---|---|
| `document` | `id_card` |
| `movil` | `phone` |
| `birth_date` | `bithdate` |
| `advisor_name` | `seller` |
| `beneficiaries[].full_name` | `membership_form_beneficiaries.name` |
| `department_id` | (ignorar) |
| `recaptcha_token` | (ignorar) |

El campo `date` se genera en el backend con `today()`.

---

## Task 1: Corregir modelo MembershipForm

**Files:**
- Modify: `app/Models/MembershipForm.php`

- [ ] **Step 1: Corregir `$fillable` del modelo**

Reemplazar el contenido de `app/Models/MembershipForm.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lastname',
        'id_card',
        'phone',
        'email',
        'bithdate',
        'address',
        'city_id',
        'date',
        'seller',
        'state',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function membershipFormBeneficiaries()
    {
        return $this->hasMany(MembershipFormBeneficiary::class);
    }
}
```

- [ ] **Step 2: Verificar que la migración ya está ejecutada**

```bash
php artisan migrate:status
```

Buscar `2025_09_12_035040_create_membership_forms_table` y `2025_09_12_041720_create_membership_form_beneficiaries_table` — deben estar en estado `Ran`.

---

## Task 2: Crear MembershipFormFactory

**Files:**
- Create: `database/factories/MembershipFormFactory.php`

- [ ] **Step 1: Crear el factory**

```php
<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class MembershipFormFactory extends Factory
{
    public function definition(): array
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name'       => fake()->state(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $city = City::firstOrCreate(
            ['name' => fake()->city()],
            ['department_id' => $departmentId]
        );

        return [
            'name'     => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'id_card'  => fake()->unique()->numerify('#########'),
            'phone'    => fake()->numerify('300#######'),
            'email'    => fake()->unique()->safeEmail(),
            'bithdate' => fake()->dateTimeBetween('-80 years', '-18 years')->format('Y-m-d'),
            'address'  => fake()->address(),
            'city_id'  => $city->id,
            'date'     => now()->toDateString(),
            'seller'   => fake()->name(),
            'state'    => 0,
        ];
    }
}
```

---

## Task 3: Implementar MembershipFormController — endpoint público `store()`

**Files:**
- Modify: `app/Http/Controllers/MembershipFormController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/MembershipFormPublicTest.php`

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/MembershipFormPublicTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipFormPublicTest extends TestCase
{
    use RefreshDatabase;

    private function cityId(): int
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'TestDept', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return City::create(['name' => 'TestCity', 'department_id' => $deptId])->id;
    }

    public function test_store_crea_membership_form_y_beneficiarios(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/affiliate-request', [
            'name'         => 'Juan',
            'lastname'     => 'Pérez',
            'document'     => '1234567890',
            'movil'        => '3001234567',
            'email'        => 'juan@example.com',
            'birth_date'   => '1990-05-15',
            'address'      => 'Calle 1 # 2-3',
            'city_id'      => $cityId,
            'advisor_name' => 'Carlos Asesor',
            'beneficiaries'=> [['full_name' => 'Ana Pérez']],
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Juan')
                 ->assertJsonPath('data.id_card', '1234567890')
                 ->assertJsonPath('data.state', 0);

        $this->assertDatabaseHas('membership_forms', [
            'name'    => 'Juan',
            'id_card' => '1234567890',
            'phone'   => '3001234567',
            'bithdate'=> '1990-05-15',
            'seller'  => 'Carlos Asesor',
            'state'   => 0,
        ]);

        $this->assertDatabaseHas('membership_form_beneficiaries', ['name' => 'Ana Pérez']);
    }

    public function test_store_falla_sin_campos_requeridos(): void
    {
        $response = $this->postJson('/api/public/affiliate-request', []);

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_falla_con_phone_invalido(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/affiliate-request', [
            'name'         => 'Juan',
            'lastname'     => 'Pérez',
            'document'     => '1234567890',
            'movil'        => '123',
            'email'        => 'juan@example.com',
            'address'      => 'Calle 1',
            'city_id'      => $cityId,
            'advisor_name' => 'Asesor',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.movil.0', fn($v) => str_contains($v, '10'));
    }

    public function test_store_sin_beneficiarios_es_valido(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/affiliate-request', [
            'name'         => 'María',
            'lastname'     => 'Gómez',
            'document'     => '9876543210',
            'movil'        => '3109876543',
            'email'        => 'maria@example.com',
            'address'      => 'Carrera 5 # 10',
            'city_id'      => $cityId,
            'advisor_name' => 'Asesor',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('membership_form_beneficiaries', 0);
    }
}
```

- [ ] **Step 2: Ejecutar el test — debe fallar**

```bash
php artisan test tests/Feature/MembershipFormPublicTest.php
```

Resultado esperado: FAIL (ruta no existe).

- [ ] **Step 3: Registrar la ruta pública en `routes/api.php`**

Dentro del bloque `Route::prefix('public')`, agregar al final:

```php
Route::post('affiliate-request', [MembershipFormController::class, 'store']);
```

Agregar el import al tope del archivo:

```php
use App\Http\Controllers\MembershipFormController;
```

- [ ] **Step 4: Implementar `store()` en el controlador**

En `app/Http/Controllers/MembershipFormController.php`, reemplazar el método `store`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\MembershipForm;
use App\Models\MembershipFormBeneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MembershipFormController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'lastname'        => 'required|string|max:255',
            'document'        => 'required|string|max:255',
            'movil'           => 'required|digits:10',
            'email'           => 'required|email|max:255',
            'birth_date'      => 'nullable|date',
            'address'         => 'required|string|max:255',
            'city_id'         => 'required|exists:cities,id',
            'advisor_name'    => 'required|string|max:255',
            'beneficiaries'   => 'nullable|array',
            'beneficiaries.*' => 'array',
            'beneficiaries.*.full_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $form = MembershipForm::create([
            'name'     => $request->name,
            'lastname' => $request->lastname,
            'id_card'  => $request->document,
            'phone'    => $request->movil,
            'email'    => $request->email,
            'bithdate' => $request->birth_date,
            'address'  => $request->address,
            'city_id'  => $request->city_id,
            'date'     => now()->toDateString(),
            'seller'   => $request->advisor_name,
            'state'    => 0,
        ]);

        if ($request->filled('beneficiaries')) {
            foreach ($request->beneficiaries as $b) {
                if (!empty($b['full_name'])) {
                    MembershipFormBeneficiary::create([
                        'membership_form_id' => $form->id,
                        'name'               => $b['full_name'],
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Solicitud de afiliación recibida correctamente',
            'data'    => $form,
        ], 201);
    }

    // Los demás métodos se implementan en Task 4
    public function index(Request $request) { return response()->json([]); }
    public function show($id) { return response()->json([]); }
    public function destroy($id) { return response()->json([]); }
    public function markConverted($id) { return response()->json([]); }
}
```

- [ ] **Step 5: Ejecutar los tests — deben pasar**

```bash
php artisan test tests/Feature/MembershipFormPublicTest.php
```

Resultado esperado: 4 tests, 4 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Models/MembershipForm.php \
        database/factories/MembershipFormFactory.php \
        app/Http/Controllers/MembershipFormController.php \
        routes/api.php \
        tests/Feature/MembershipFormPublicTest.php
git commit -m "feat: endpoint público POST /api/public/affiliate-request para solicitudes de afiliación"
```

---

## Task 4: Implementar endpoints protegidos del MembershipFormController

**Files:**
- Modify: `app/Http/Controllers/MembershipFormController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/MembershipFormAdminTest.php`

- [ ] **Step 1: Escribir los tests que fallan**

Crear `tests/Feature/MembershipFormAdminTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\MembershipForm;
use App\Models\MembershipFormBeneficiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFormAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['type' => 1]);
    }

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/membership-forms')->assertStatus(401);
    }

    public function test_index_retorna_solo_pendientes(): void
    {
        $admin = $this->admin();
        MembershipForm::factory()->count(3)->create(['state' => 0]);
        MembershipForm::factory()->count(2)->create(['state' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/membership-forms');

        $response->assertStatus(200)
                 ->assertJsonPath('meta.total', 3)
                 ->assertJsonStructure(['message', 'data', 'meta']);
    }

    public function test_index_busca_por_nombre(): void
    {
        $admin = $this->admin();
        MembershipForm::factory()->create(['name' => 'Carlos', 'lastname' => 'Torres', 'state' => 0]);
        MembershipForm::factory()->create(['name' => 'Ana',    'lastname' => 'López',  'state' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/membership-forms?search=Carlos');

        $response->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_index_busca_por_cedula(): void
    {
        $admin = $this->admin();
        MembershipForm::factory()->create(['id_card' => '1122334455', 'state' => 0]);
        MembershipForm::factory()->create(['id_card' => '9988776655', 'state' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/membership-forms?search=1122334455');

        $response->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_show_retorna_form_con_beneficiarios(): void
    {
        $admin = $this->admin();
        $form  = MembershipForm::factory()->create(['state' => 0]);
        MembershipFormBeneficiary::create(['membership_form_id' => $form->id, 'name' => 'Hijo 1']);

        $response = $this->actingAs($admin)->getJson("/api/membership-forms/{$form->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $form->id)
                 ->assertJsonCount(1, 'data.membership_form_beneficiaries');
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())->getJson('/api/membership-forms/9999')->assertStatus(404);
    }

    public function test_destroy_elimina_el_registro(): void
    {
        $admin = $this->admin();
        $form  = MembershipForm::factory()->create(['state' => 0]);

        $this->actingAs($admin)->deleteJson("/api/membership-forms/{$form->id}")->assertStatus(200);
        $this->assertDatabaseMissing('membership_forms', ['id' => $form->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())->deleteJson('/api/membership-forms/9999')->assertStatus(404);
    }

    public function test_mark_converted_cambia_state_a_1(): void
    {
        $admin = $this->admin();
        $form  = MembershipForm::factory()->create(['state' => 0]);

        $this->actingAs($admin)->patchJson("/api/membership-forms/{$form->id}/convert")->assertStatus(200);
        $this->assertDatabaseHas('membership_forms', ['id' => $form->id, 'state' => 1]);
    }

    public function test_mark_converted_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())->patchJson('/api/membership-forms/9999/convert')->assertStatus(404);
    }
}
```

- [ ] **Step 2: Ejecutar los tests — deben fallar**

```bash
php artisan test tests/Feature/MembershipFormAdminTest.php
```

Resultado esperado: FAIL (rutas no existen).

- [ ] **Step 3: Registrar las rutas protegidas en `routes/api.php`**

Dentro del bloque `Route::middleware('auth:sanctum')`, agregar (la ruta estática `convert` ANTES del apiResource):

```php
// Solicitudes de afiliación
Route::patch('membership-forms/{id}/convert', [MembershipFormController::class, 'markConverted']);
Route::apiResource('membership-forms', MembershipFormController::class)->only(['index', 'show', 'destroy']);
```

- [ ] **Step 4: Implementar los métodos protegidos en el controlador**

Reemplazar `app/Http/Controllers/MembershipFormController.php` completo:

```php
<?php

namespace App\Http\Controllers;

use App\Models\MembershipForm;
use App\Models\MembershipFormBeneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MembershipFormController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $search  = $request->query('search', '');

        $query = MembershipForm::with(['city:id,name'])
            ->select('membership_forms.*')
            ->where('state', 0);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',     'LIKE', "%{$search}%")
                  ->orWhere('lastname', 'LIKE', "%{$search}%")
                  ->orWhere('id_card',  'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $paginated = $query->paginate($perPage);

        return response()->json([
            'message' => 'Solicitudes obtenidas correctamente',
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    public function show($id)
    {
        $form = MembershipForm::with([
            'city:id,name,department_id',
            'membershipFormBeneficiaries',
        ])->find($id);

        if (!$form) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        return response()->json([
            'message' => 'Solicitud obtenida correctamente',
            'data'    => $form,
        ], 200);
    }

    public function destroy($id)
    {
        $form = MembershipForm::find($id);

        if (!$form) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $form->delete();

        return response()->json(['message' => 'Solicitud eliminada correctamente'], 200);
    }

    public function markConverted($id)
    {
        $form = MembershipForm::find($id);

        if (!$form) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $form->state = 1;
        $form->save();

        return response()->json([
            'message' => 'Solicitud marcada como convertida',
            'data'    => $form,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'lastname'        => 'required|string|max:255',
            'document'        => 'required|string|max:255',
            'movil'           => 'required|digits:10',
            'email'           => 'required|email|max:255',
            'birth_date'      => 'nullable|date',
            'address'         => 'required|string|max:255',
            'city_id'         => 'required|exists:cities,id',
            'advisor_name'    => 'required|string|max:255',
            'beneficiaries'   => 'nullable|array',
            'beneficiaries.*' => 'array',
            'beneficiaries.*.full_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $form = MembershipForm::create([
            'name'     => $request->name,
            'lastname' => $request->lastname,
            'id_card'  => $request->document,
            'phone'    => $request->movil,
            'email'    => $request->email,
            'bithdate' => $request->birth_date,
            'address'  => $request->address,
            'city_id'  => $request->city_id,
            'date'     => now()->toDateString(),
            'seller'   => $request->advisor_name,
            'state'    => 0,
        ]);

        if ($request->filled('beneficiaries')) {
            foreach ($request->beneficiaries as $b) {
                if (!empty($b['full_name'])) {
                    MembershipFormBeneficiary::create([
                        'membership_form_id' => $form->id,
                        'name'               => $b['full_name'],
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'Solicitud de afiliación recibida correctamente',
            'data'    => $form,
        ], 201);
    }
}
```

- [ ] **Step 5: Ejecutar todos los tests del módulo — deben pasar**

```bash
php artisan test tests/Feature/MembershipFormPublicTest.php tests/Feature/MembershipFormAdminTest.php
```

Resultado esperado: 12 tests, 12 passed.

- [ ] **Step 6: Ejecutar la suite completa para detectar regresiones**

```bash
php artisan test
```

Resultado esperado: todos los tests en verde.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/MembershipFormController.php \
        routes/api.php \
        tests/Feature/MembershipFormAdminTest.php
git commit -m "feat: endpoints protegidos para gestión de solicitudes de afiliación"
```

---

## Task 5: Frontend — `fetch.ts` del módulo membership-forms

**Files:**
- Create: `src/app/4dnn1n/membership-forms/fetch.ts`

- [ ] **Step 1: Crear `fetch.ts`**

```typescript
import { apiFetch, csrf } from "@/lib/api";
import { memCache, TTL_LIST } from "@/lib/memCache";

export type ApiMembershipFormBeneficiary = {
  id?: number;
  membership_form_id?: number;
  name: string;
};

export type ApiMembershipForm = {
  id: number;
  name: string;
  lastname: string;
  id_card: string;
  phone: string;
  email: string;
  bithdate?: string | null;
  address: string;
  city_id: number;
  date: string;
  seller: string;
  state: number;
  city?: { id: number; name: string; department_id?: number } | null;
  membership_form_beneficiaries?: ApiMembershipFormBeneficiary[];
  created_at?: string;
  updated_at?: string;
};

export type MembershipFormMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type ApiResponse<T> = { message: string; data: T };
type ListResponse = { data: ApiMembershipForm[]; meta: MembershipFormMeta };

export async function getMembershipForms(params?: {
  search?: string;
  page?: number;
  per_page?: number;
  stade?: string;
}): Promise<ListResponse> {
  const qs = new URLSearchParams();
  if (params?.search) qs.set("search", params.search);
  if (params?.page) qs.set("page", String(params.page));
  if (params?.per_page) qs.set("per_page", String(params.per_page));
  const query = qs.toString() ? `?${qs.toString()}` : "";
  const key = `membership-forms:list:${query}`;
  return memCache.get(key, TTL_LIST, async () => {
    const res = await apiFetch<{ message: string; data: ApiMembershipForm[]; meta: MembershipFormMeta }>(
      `/api/membership-forms${query}`,
    );
    return { data: res.data ?? [], meta: res.meta };
  });
}

export async function getMembershipForm(id: number): Promise<ApiMembershipForm> {
  const res = await apiFetch<ApiResponse<ApiMembershipForm>>(`/api/membership-forms/${id}`);
  return res.data;
}

export async function deleteMembershipForm(id: number): Promise<void> {
  await csrf();
  await apiFetch(`/api/membership-forms/${id}`, { method: "DELETE" });
  memCache.invalidatePrefix("membership-forms:list:");
}

export async function markMembershipFormConverted(id: number): Promise<void> {
  await csrf();
  await apiFetch(`/api/membership-forms/${id}/convert`, { method: "PATCH" });
  memCache.invalidatePrefix("membership-forms:list:");
}
```

---

## Task 6: Frontend — `columns.tsx` del módulo membership-forms

**Files:**
- Create: `src/app/4dnn1n/membership-forms/_components/columns.tsx`

- [ ] **Step 1: Crear `columns.tsx`**

```typescript
"use client";

import type { ColumnDef } from "@tanstack/react-table";
import type { ApiMembershipForm } from "../fetch";
import { UserPlus, Trash2 } from "lucide-react";
import { useRouter } from "next/navigation";

export function buildMembershipFormColumns({
  onDelete,
}: {
  onDelete: (form: ApiMembershipForm) => void;
}): ColumnDef<ApiMembershipForm>[] {
  return [
    {
      id: "full_name",
      header: "Nombre",
      accessorFn: (row) => `${row.name} ${row.lastname}`,
      cell: ({ row }) => (
        <div className="font-medium">
          {row.original.name} {row.original.lastname}
        </div>
      ),
    },
    {
      accessorKey: "id_card",
      header: "Cédula",
      cell: ({ row }) => row.original.id_card,
    },
    {
      accessorKey: "phone",
      header: "Celular",
      cell: ({ row }) => row.original.phone,
    },
    {
      id: "city",
      header: "Ciudad",
      accessorFn: (row) => row.city?.name ?? "",
      cell: ({ row }) => row.original.city?.name ?? "-",
    },
    {
      accessorKey: "seller",
      header: "Asesor",
      cell: ({ row }) => row.original.seller,
    },
    {
      accessorKey: "date",
      header: "Fecha solicitud",
      cell: ({ row }) => {
        if (!row.original.date) return "-";
        const [y, m, d] = row.original.date.split("-");
        return `${d}/${m}/${y}`;
      },
    },
    {
      id: "actions",
      header: () => <div className="text-center">Acciones</div>,
      cell: function ActionsCell({ row }) {
        const router = useRouter();
        const form = row.original;
        return (
          <div className="flex items-center justify-center gap-2">
            <button
              onClick={() => router.push(`/4dnn1n/affiliates/new?from=${form.id}`)}
              title="Crear afiliado"
              className="inline-flex items-center gap-1 rounded-md bg-primary px-2 py-1 text-xs font-medium text-white hover:bg-primary/90"
            >
              <UserPlus className="h-3.5 w-3.5" />
              Crear afiliado
            </button>
            <button
              onClick={() => onDelete(form)}
              title="Eliminar solicitud"
              className="inline-flex items-center gap-1 rounded-md border border-red-300 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
            >
              <Trash2 className="h-3.5 w-3.5" />
              Eliminar
            </button>
          </div>
        );
      },
    },
  ];
}
```

---

## Task 7: Frontend — página principal `membership-forms/page.tsx` + sidebar

**Files:**
- Create: `src/app/4dnn1n/membership-forms/page.tsx`
- Modify: `src/components/Layouts/sidebar/data/index.ts`

- [ ] **Step 1: Crear `page.tsx`**

```typescript
"use client";

import { useMemo } from "react";
import { DataTable } from "@/components/data-table/DataTable";
import { LoadingOverlay } from "@/components/LoadingOverlay";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useServerTable } from "@/hooks/useServerTable";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";

import { getMembershipForms, deleteMembershipForm, type ApiMembershipForm } from "./fetch";
import { buildMembershipFormColumns } from "./_components/columns";

export default function MembershipFormsPage() {
  usePageTitle("Solicitudes de Afiliación");

  const { data, setData, setMeta, tableProps } = useServerTable<ApiMembershipForm>(
    getMembershipForms,
    { defaultStade: "all" },
  );

  const onDelete = async (form: ApiMembershipForm) => {
    try {
      const ok = await alert.confirm({
        title: "¿Eliminar solicitud?",
        text: `Se eliminará la solicitud de ${form.name} ${form.lastname}. Esta acción no se puede deshacer.`,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        onConfirm: async () => {
          setData((prev) => prev.filter((x) => x.id !== form.id));
          setMeta((m) => ({ ...m, total: m.total - 1 }));
          await deleteMembershipForm(form.id);
        },
      });
      if (ok) {
        await alert.success("Eliminado", "Solicitud eliminada correctamente.");
      }
    } catch (err) {
      setData((prev) => [...prev, form]);
      await alert.error("Error", getApiErrorMessage(err));
    }
  };

  const columns = useMemo(
    () => buildMembershipFormColumns({ onDelete }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  return (
    <>
      <LoadingOverlay isLoading={tableProps.loading ?? false} />
      <DataTable
        title="Solicitudes de Afiliación"
        columns={columns}
        {...tableProps}
        enableStateFilter={false}
        searchPlaceholder="Buscar por nombre o cédula..."
      />
    </>
  );
}
```

- [ ] **Step 2: Actualizar URL de "Afiliaciones" en el sidebar**

En `src/components/Layouts/sidebar/data/index.ts`, localizar el ítem con `title: "Afiliaciones"` y cambiar `url: ""` a `url: "/4dnn1n/membership-forms"`:

```typescript
{
  title: "Afiliaciones",
  icon: FileText,
  url: "/4dnn1n/membership-forms",
  items: [],
},
```

- [ ] **Step 3: Commit**

```bash
git add src/app/4dnn1n/membership-forms/ \
        src/components/Layouts/sidebar/data/index.ts
git commit -m "feat: módulo Solicitudes de Afiliación en el panel admin"
```

---

## Task 8: Frontend — pre-carga en `/affiliates/new?from={id}`

**Files:**
- Modify: `src/app/4dnn1n/affiliates/new/page.tsx`
- Modify: `src/app/4dnn1n/affiliates/fetch.ts`

- [ ] **Step 1: Agregar `markMembershipFormConverted` al fetch de afiliados**

En `src/app/4dnn1n/affiliates/fetch.ts`, agregar al final del archivo:

```typescript
export async function markMembershipFormConverted(id: number): Promise<void> {
  await csrf();
  await apiFetch(`/api/membership-forms/${id}/convert`, { method: "PATCH" });
}
```

> Nota: importar desde fetch.ts de afiliados para que `new/page.tsx` no tenga una importación cruzada entre módulos.

- [ ] **Step 2: Modificar `new/page.tsx` para leer `?from` y pre-cargar datos**

Reemplazar el contenido de `src/app/4dnn1n/affiliates/new/page.tsx`:

```typescript
"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { ArrowLeft } from "lucide-react";

import {
  createAffiliate,
  markMembershipFormConverted,
  type CreateAffiliatePayload,
  type ApiAffiliate,
} from "../fetch";
import { Button } from "@/components/ui-elements/button";
import { ShowcaseSection } from "@/components/Layouts/showcase-section";
import AffiliateForm from "../_components/AffiliateForm";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useAuth } from "@/context/AuthContext";
import { apiFetch } from "@/lib/api";

type MembershipFormData = {
  id: number;
  name: string;
  lastname: string;
  id_card: string;
  phone: string;
  email: string;
  bithdate?: string | null;
  address: string;
  city_id: number;
  city?: { id: number; name: string; department_id?: number } | null;
  membership_form_beneficiaries?: { name: string }[];
};

export default function NewAffiliatePage() {
  usePageTitle("Crear Afiliado");
  const router = useRouter();
  const searchParams = useSearchParams();
  const fromId = searchParams.get("from");
  const { user, loading } = useAuth();

  const [prefill, setPrefill] = useState<Partial<ApiAffiliate> | undefined>(undefined);
  const [loadingPrefill, setLoadingPrefill] = useState(!!fromId);

  useEffect(() => {
    if (!fromId) return;
    apiFetch<{ data: MembershipFormData }>(`/api/membership-forms/${fromId}`)
      .then(({ data }) => {
        setPrefill({
          name:      data.name,
          lastname:  data.lastname,
          id_card:   data.id_card,
          movil:     data.phone,
          email:     data.email,
          bithdate:  data.bithdate ?? undefined,
          address:   data.address,
          city_id:   data.city_id,
          city:      data.city ?? undefined,
          beneficiaries: data.membership_form_beneficiaries?.map((b) => ({ name: b.name })) ?? [],
        });
      })
      .catch(() => {
        // Si falla la pre-carga, continuar con el formulario vacío
      })
      .finally(() => setLoadingPrefill(false));
  }, [fromId]);

  if (loading || loadingPrefill) return null;

  if (user?.type !== 1 && user?.type !== 2) {
    return (
      <div className="flex h-64 items-center justify-center p-6 text-red-500 font-medium">
        No tienes permisos suficientes para acceder a esta vista.
      </div>
    );
  }

  const backHref = fromId ? "/4dnn1n/membership-forms" : "/4dnn1n/affiliates";

  const handleCreate = async (payload: CreateAffiliatePayload) => {
    try {
      const ok = await alert.confirm({
        title: "¿Crear afiliado?",
        text: "Se guardará la información del afiliado y sus beneficiarios.",
        confirmButtonText: "Sí, crear",
        cancelButtonText: "Cancelar",
        onConfirm: () => createAffiliate(payload),
      });
      if (ok) {
        if (fromId) {
          await markMembershipFormConverted(Number(fromId)).catch(() => {});
        }
        await alert.success("Creado", "Afiliado registrado correctamente.");
        router.push(fromId ? "/4dnn1n/membership-forms" : "/4dnn1n/affiliates");
      }
    } catch (error) {
      await alert.error("Error", getApiErrorMessage(error));
    }
  };

  return (
    <>
      <ShowcaseSection
        title="Crear Afiliado / Usuario"
        description="Completa la información"
        actions={
          <Link href={backHref}>
            <Button
              type="button"
              className="inline-flex items-center gap-2 rounded-lg border border-stroke px-4 py-2 font-medium text-dark hover:shadow-1 dark:border-dark-3 dark:text-white"
            >
              <ArrowLeft className="h-4 w-4" />
              Volver
            </Button>
          </Link>
        }
      >
        <AffiliateForm mode="create" initial={prefill} onSubmit={handleCreate} />
      </ShowcaseSection>
    </>
  );
}
```

- [ ] **Step 3: Verificar que `AffiliateForm` no requiere cambios**

El componente ya acepta `initial?: Partial<ApiAffiliate>` y pre-llena:
- `name`, `lastname`, `id_card`, `address`, `bithdate`, `movil`, `email`, `city_id` desde `initial`
- `city.department_id` para pre-seleccionar el departamento (ya tiene el `useEffect` que lee `initial?.city?.department_id`)
- `beneficiaries` desde `initial?.beneficiaries`

No se necesita ningún cambio en `AffiliateForm.tsx`.

- [ ] **Step 4: Commit**

```bash
git add src/app/4dnn1n/affiliates/new/page.tsx \
        src/app/4dnn1n/affiliates/fetch.ts
git commit -m "feat: pre-carga datos de solicitud de afiliación en formulario de nuevo afiliado"
```

---

## Self-Review

### Cobertura del spec

| Requisito | Task |
|---|---|
| Modelo corregido (`state` en `$fillable`, `add` eliminado) | Task 1 |
| Factory para tests | Task 2 |
| `POST /api/public/affiliate-request` | Task 3 |
| `GET /api/membership-forms` (paginado, búsqueda, solo `state=0`) | Task 4 |
| `GET /api/membership-forms/{id}` con beneficiarios | Task 4 |
| `DELETE /api/membership-forms/{id}` | Task 4 |
| `PATCH /api/membership-forms/{id}/convert` | Task 4 |
| Ruta `convert` antes de `apiResource` | Task 4, Step 3 |
| Frontend `fetch.ts` | Task 5 |
| Tabla con columnas correctas | Task 6 |
| Módulo `/4dnn1n/membership-forms` | Task 7 |
| Sidebar "Afiliaciones" con URL | Task 7 |
| Pre-carga `?from={id}` en nuevo afiliado | Task 8 |
| Mapeo `phone → movil`, `city.department_id` para dept pre-select | Task 8 |
| `markConverted` post-guardado + redirección a `/membership-forms` | Task 8 |
| Formulario público ya existente — solo necesita el endpoint | Task 3 |

### Consistencia de tipos

- `ApiMembershipForm.membership_form_beneficiaries` definido en Task 5 y usado en Task 8 ✓
- `markMembershipFormConverted` definido en Task 5 (fetch de membership) y reutilizado en Task 8 via `affiliates/fetch.ts` ✓
- `buildMembershipFormColumns` recibe `{ onDelete }` en Task 6 y se construye así en Task 7 ✓
- El `useServerTable` recibe `getMembershipForms` que acepta `{ stade, search, page, per_page }` — el parámetro `stade` se ignora en el backend (siempre filtra `state=0`) ✓