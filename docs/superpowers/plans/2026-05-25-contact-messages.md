# Contact Messages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Conectar el formulario de contacto público al panel admin: guardar mensajes en DB y mostrarlos en una tabla con detalle y eliminación.

**Architecture:** Se edita la migración existente (`contacts`) para alinear el esquema con el formulario público (reemplazar `address` con `subject`). El `ContactController` implementa `store` público e `index/show/destroy` admin. En el frontend se crea el módulo `/4dnn1n/contacts` siguiendo exactamente el patrón de `membership-forms`.

**Tech Stack:** Laravel 11, PHP, MySQL, Next.js 14, TypeScript, TanStack Table, shadcn/ui, Lucide

> ⚠️ **NO hacer commits** en ninguna tarea. Solo cuando el usuario lo apruebe explícitamente al final.

---

## Mapa de archivos

| Acción | Archivo |
|---|---|
| Modificar | `database/migrations/2025_09_12_043132_create_contacts_table.php` |
| Modificar | `app/Models/Contact.php` |
| Modificar | `app/Http/Controllers/ContactController.php` |
| Modificar | `routes/api.php` |
| Crear | `tests/Feature/ContactTest.php` |
| Crear | `frontend-cm/src/app/4dnn1n/contacts/fetch.ts` |
| Crear | `frontend-cm/src/app/4dnn1n/contacts/_components/columns.tsx` |
| Crear | `frontend-cm/src/app/4dnn1n/contacts/page.tsx` |
| Crear | `frontend-cm/src/app/4dnn1n/contacts/[id]/page.tsx` |
| Modificar | `frontend-cm/src/components/Layouts/sidebar/data/index.ts` |

---

## Task 1: Corregir la migración de contacts

**Files:**
- Modify: `database/migrations/2025_09_12_043132_create_contacts_table.php`

- [ ] **Step 1: Editar la migración** — reemplazar `address` con `subject`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contacts');
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->unsignedBigInteger('city_id');
            $table->string('subject');
            $table->text('comment');
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
```

- [ ] **Step 2: Correr el refresh de migraciones**

```bash
php artisan migrate:refresh
```

Expected: todas las migraciones corren sin errores. La tabla `contacts` queda con las columnas `name`, `email`, `phone`, `city_id`, `subject`, `comment`, `created_at`, `updated_at`.

---

## Task 2: Actualizar el modelo Contact

**Files:**
- Modify: `app/Models/Contact.php`

- [ ] **Step 1: Actualizar `$fillable`** — quitar `address` y `date`, agregar `subject`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'city_id',
        'subject',
        'comment',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
```

---

## Task 3: Implementar ContactController

**Files:**
- Modify: `app/Http/Controllers/ContactController.php`

- [ ] **Step 1: Implementar los cuatro métodos**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'movil'   => 'required|digits:10',
            'email'   => 'required|email|max:255',
            'asunto'  => 'required|string|max:255',
            'city_id' => 'required|exists:cities,id',
            'mensaje' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $contact = Contact::create([
            'name'    => $request->name,
            'email'   => $request->email,
            'phone'   => $request->movil,
            'city_id' => $request->city_id,
            'subject' => $request->asunto,
            'comment' => $request->mensaje,
        ]);

        return response()->json([
            'message' => 'Mensaje de contacto recibido correctamente',
            'data'    => $contact,
        ], 201);
    }

    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $search  = $request->query('search', '');

        $query = Contact::with(['city:id,name'])
            ->select('contacts.*');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',  'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('id', 'desc');

        $paginated = $query->paginate($perPage);

        return response()->json([
            'message' => 'Mensajes de contacto obtenidos correctamente',
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
        $contact = Contact::with(['city:id,name'])->find($id);

        if (!$contact) {
            return response()->json(['message' => 'Mensaje no encontrado'], 404);
        }

        return response()->json([
            'message' => 'Mensaje obtenido correctamente',
            'data'    => $contact,
        ], 200);
    }

    public function destroy($id)
    {
        $contact = Contact::find($id);

        if (!$contact) {
            return response()->json(['message' => 'Mensaje no encontrado'], 404);
        }

        $contact->delete();

        return response()->json(['message' => 'Mensaje eliminado correctamente'], 200);
    }
}
```

---

## Task 4: Registrar las rutas

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Agregar import de ContactController** en el bloque de `use` al inicio del archivo

```php
use App\Http\Controllers\ContactController;
```

- [ ] **Step 2: Agregar ruta pública** dentro del grupo `Route::prefix('public')`, después de `affiliate-request`

```php
Route::post('contact', [ContactController::class, 'store']);
```

El grupo público quedará así:
```php
Route::prefix('public')->group(function () {
    Route::get('doctors', [DoctorController::class, 'publicIndex']);
    Route::get('specialties', [SpecialtyController::class, 'publicIndex']);
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{department}/cities', [CityController::class, 'getByDepartment']);
    Route::post('affiliate-request', [MembershipFormController::class, 'store']);
    Route::post('contact', [ContactController::class, 'store']);
});
```

- [ ] **Step 3: Agregar rutas admin** dentro del grupo `auth:sanctum`, al final (antes del cierre `}`), después de las rutas de membership-forms

```php
// Mensajes de contacto
Route::apiResource('contacts', ContactController::class)->only(['index', 'show', 'destroy']);
```

- [ ] **Step 4: Verificar rutas registradas**

```bash
php artisan route:list --path=contacts
```

Expected:
```
GET|HEAD  api/contacts          contacts.index
GET|HEAD  api/contacts/{contact} contacts.show
DELETE    api/contacts/{contact} contacts.destroy
POST      api/public/contact    ContactController@store
```

---

## Task 5: Tests del backend

**Files:**
- Create: `tests/Feature/ContactTest.php`
- Create: `database/factories/ContactFactory.php`

- [ ] **Step 1: Crear el factory** — `database/factories/ContactFactory.php`

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'    => $this->faker->name(),
            'email'   => $this->faker->safeEmail(),
            'phone'   => $this->faker->numerify('##########'),
            'city_id' => 1,
            'subject' => $this->faker->randomElement([
                'Información sobre planes',
                'Soporte técnico',
                'Quejas y reclamos',
                'Solicitud de información',
                'Otro',
            ]),
            'comment' => $this->faker->paragraph(),
        ];
    }
}
```

- [ ] **Step 2: Crear los tests** — `tests/Feature/ContactTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function cityId(): int
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'TestDept', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return City::create(['name' => 'TestCity', 'department_id' => $deptId])->id;
    }

    private function adminUser(): User
    {
        return User::factory()->create(['type' => 1, 'state' => 1]);
    }

    // ── Endpoint público ──

    public function test_store_guarda_mensaje_de_contacto(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/contact', [
            'name'    => 'Juan García',
            'movil'   => '3001234567',
            'email'   => 'juan@example.com',
            'asunto'  => 'Información sobre planes',
            'city_id' => $cityId,
            'mensaje' => 'Hola, quiero información sobre los planes disponibles.',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Juan García')
                 ->assertJsonPath('data.subject', 'Información sobre planes');

        $this->assertDatabaseHas('contacts', [
            'name'    => 'Juan García',
            'email'   => 'juan@example.com',
            'phone'   => '3001234567',
            'subject' => 'Información sobre planes',
            'comment' => 'Hola, quiero información sobre los planes disponibles.',
        ]);
    }

    public function test_store_falla_sin_campos_requeridos(): void
    {
        $response = $this->postJson('/api/public/contact', []);

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_falla_con_movil_invalido(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/contact', [
            'name'    => 'Juan García',
            'movil'   => '123',
            'email'   => 'juan@example.com',
            'asunto'  => 'Otro',
            'city_id' => $cityId,
            'mensaje' => 'Mensaje de prueba largo suficiente.',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.movil.0', fn($v) => str_contains($v, '10'));
    }

    public function test_store_falla_con_mensaje_corto(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/contact', [
            'name'    => 'Juan García',
            'movil'   => '3001234567',
            'email'   => 'juan@example.com',
            'asunto'  => 'Otro',
            'city_id' => $cityId,
            'mensaje' => 'Corto',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['mensaje']]);
    }

    // ── Endpoints admin ──

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/contacts')->assertStatus(401);
    }

    public function test_index_retorna_lista_paginada(): void
    {
        $cityId = $this->cityId();
        Contact::factory()->count(3)->create(['city_id' => $cityId]);

        $response = $this->actingAs($this->adminUser())
                         ->getJson('/api/contacts');

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
                 ->assertJsonCount(3, 'data');
    }

    public function test_index_busca_por_nombre(): void
    {
        $cityId = $this->cityId();
        Contact::factory()->create(['name' => 'Ana Martínez', 'city_id' => $cityId]);
        Contact::factory()->create(['name' => 'Pedro López',  'city_id' => $cityId]);

        $response = $this->actingAs($this->adminUser())
                         ->getJson('/api/contacts?search=Ana');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Ana Martínez');
    }

    public function test_show_retorna_contacto_con_ciudad(): void
    {
        $cityId  = $this->cityId();
        $contact = Contact::factory()->create(['city_id' => $cityId]);

        $response = $this->actingAs($this->adminUser())
                         ->getJson("/api/contacts/{$contact->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $contact->id)
                 ->assertJsonStructure(['data' => ['city']]);
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->adminUser())
             ->getJson('/api/contacts/99999')
             ->assertStatus(404);
    }

    public function test_destroy_elimina_fisicamente(): void
    {
        $cityId  = $this->cityId();
        $contact = Contact::factory()->create(['city_id' => $cityId]);

        $this->actingAs($this->adminUser())
             ->deleteJson("/api/contacts/{$contact->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->adminUser())
             ->deleteJson('/api/contacts/99999')
             ->assertStatus(404);
    }
}
```

- [ ] **Step 3: Correr los tests**

```bash
php artisan test tests/Feature/ContactTest.php
```

Expected: 9 tests, 9 passed.

---

## Task 6: fetch.ts del frontend

**Files:**
- Create: `frontend-cm/src/app/4dnn1n/contacts/fetch.ts`

- [ ] **Step 1: Crear el archivo**

```ts
import { apiFetch, csrf } from "@/lib/api";
import { memCache, TTL_LIST } from "@/lib/memCache";

export type ApiContact = {
  id: number;
  name: string;
  email: string;
  phone: string;
  city_id: number;
  subject: string;
  comment: string;
  created_at: string;
  updated_at: string;
  city?: { id: number; name: string } | null;
};

export type ContactMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type ApiResponse<T> = { message: string; data: T };
type ListResponse = { data: ApiContact[]; meta: ContactMeta };

export async function getContacts(params?: {
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
  const key = `contacts:list:${query}`;
  return memCache.get(key, TTL_LIST, async () => {
    const res = await apiFetch<{ message: string; data: ApiContact[]; meta: ContactMeta }>(
      `/api/contacts${query}`,
    );
    return { data: res.data ?? [], meta: res.meta };
  });
}

export async function getContact(id: number): Promise<ApiContact> {
  const res = await apiFetch<ApiResponse<ApiContact>>(`/api/contacts/${id}`);
  return res.data;
}

export async function deleteContact(id: number): Promise<void> {
  await csrf();
  await apiFetch(`/api/contacts/${id}`, { method: "DELETE" });
  memCache.invalidatePrefix("contacts:list:");
}
```

---

## Task 7: columns.tsx del frontend

**Files:**
- Create: `frontend-cm/src/app/4dnn1n/contacts/_components/columns.tsx`

- [ ] **Step 1: Crear el archivo**

```tsx
"use client";

import type { ColumnDef } from "@tanstack/react-table";
import type { ApiContact } from "../fetch";
import { Eye, Trash2 } from "lucide-react";
import Link from "next/link";

export function buildContactColumns({
  onDelete,
}: {
  onDelete: (contact: ApiContact) => void;
}): ColumnDef<ApiContact>[] {
  return [
    {
      accessorKey: "name",
      header: "Nombre",
      cell: ({ row }) => (
        <div className="font-medium text-left whitespace-normal max-w-[180px] break-words">
          {row.original.name}
        </div>
      ),
    },
    {
      accessorKey: "email",
      header: "Correo",
      cell: ({ row }) => (
        <div className="whitespace-nowrap text-sm">{row.original.email}</div>
      ),
    },
    {
      id: "city",
      header: "Ciudad",
      accessorFn: (row) => row.city?.name ?? "",
      cell: ({ row }) => <div>{row.original.city?.name ?? "-"}</div>,
    },
    {
      accessorKey: "phone",
      header: "Teléfono",
      cell: ({ row }) => <div className="whitespace-nowrap">{row.original.phone}</div>,
    },
    {
      accessorKey: "subject",
      header: "Asunto",
      cell: ({ row }) => (
        <div className="whitespace-normal max-w-[160px] break-words text-sm">
          {row.original.subject}
        </div>
      ),
    },
    {
      accessorKey: "comment",
      header: "Mensaje",
      cell: ({ row }) => {
        const text = row.original.comment;
        const truncated = text.length > 80 ? `${text.slice(0, 80)}…` : text;
        return (
          <div className="whitespace-normal max-w-[200px] break-words text-sm text-dark-5 dark:text-dark-6">
            {truncated}
          </div>
        );
      },
    },
    {
      accessorKey: "created_at",
      header: "Fecha",
      cell: ({ row }) => {
        const raw = row.original.created_at;
        if (!raw) return <div>-</div>;
        const date = new Date(raw);
        const d = String(date.getDate()).padStart(2, "0");
        const m = String(date.getMonth() + 1).padStart(2, "0");
        const y = date.getFullYear();
        return <div className="whitespace-nowrap">{`${d}/${m}/${y}`}</div>;
      },
    },
    {
      id: "actions",
      header: () => <div className="text-center">Acciones</div>,
      cell: ({ row }) => {
        const contact = row.original;
        return (
          <div className="grid w-fit grid-cols-2 place-items-center gap-1 mx-auto">
            <Link
              href={`/4dnn1n/contacts/${contact.id}`}
              className="hover:bg-muted rounded-md p-2"
              title="Ver detalle"
              aria-label="Ver detalle"
            >
              <Eye className="h-4 w-4 text-primary" />
            </Link>
            <button
              type="button"
              onClick={() => onDelete(contact)}
              className="hover:bg-muted rounded-md p-2"
              title="Eliminar mensaje"
              aria-label="Eliminar mensaje"
            >
              <Trash2 className="h-4 w-4 text-red-500" />
            </button>
          </div>
        );
      },
      meta: { stickyRight: true },
    },
  ];
}
```

---

## Task 8: page.tsx de la lista

**Files:**
- Create: `frontend-cm/src/app/4dnn1n/contacts/page.tsx`

- [ ] **Step 1: Crear el archivo**

```tsx
"use client";

import { useMemo } from "react";
import { DataTable } from "@/components/data-table/DataTable";
import { LoadingOverlay } from "@/components/LoadingOverlay";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useServerTable } from "@/hooks/useServerTable";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";

import { getContacts, deleteContact, type ApiContact } from "./fetch";
import { buildContactColumns } from "./_components/columns";

export default function ContactsPage() {
  usePageTitle("Mensajes de Contacto");

  const { data, setData, setMeta, tableProps, isInitialLoad } = useServerTable<ApiContact>(
    getContacts,
    { defaultStade: "all" },
  );

  const onDelete = async (contact: ApiContact) => {
    try {
      const ok = await alert.confirm({
        title: "¿Eliminar mensaje?",
        text: `Se eliminará el mensaje de ${contact.name}. Esta acción no se puede deshacer.`,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        onConfirm: async () => {
          setData((prev) => prev.filter((x) => x.id !== contact.id));
          setMeta((m) => ({ ...m, total: m.total - 1 }));
          await deleteContact(contact.id);
        },
      });
      if (ok) {
        await alert.success("Eliminado", "Mensaje eliminado correctamente.");
      }
    } catch (err) {
      setData((prev) => [...prev, contact]);
      await alert.error("Error", getApiErrorMessage(err));
    }
  };

  const columns = useMemo(
    () => buildContactColumns({ onDelete }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  return (
    <>
      <LoadingOverlay isLoading={tableProps.loading && isInitialLoad} />
      <DataTable
        title="Mensajes de Contacto"
        columns={columns}
        {...tableProps}
        enableStateFilter={false}
      />
    </>
  );
}
```

---

## Task 9: Página de detalle /contacts/[id]

**Files:**
- Create: `frontend-cm/src/app/4dnn1n/contacts/[id]/page.tsx`

- [ ] **Step 1: Crear el archivo**

```tsx
"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { ArrowLeft, User, Mail, Phone, MapPin, MessageSquare, Tag, Calendar } from "lucide-react";
import { ShowcaseSection } from "@/components/Layouts/showcase-section";
import { Button } from "@/components/ui-elements/button";
import { usePageTitle } from "@/hooks/usePageTitle";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import { getContact, deleteContact, type ApiContact } from "../fetch";

function Field({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode;
  label: string;
  value: React.ReactNode;
}) {
  return (
    <div className="flex items-start gap-3 rounded-xl border border-stroke bg-background p-4 dark:border-dark-3">
      <span className="mt-0.5 shrink-0 text-primary">{icon}</span>
      <div className="min-w-0">
        <p className="text-xs font-medium uppercase tracking-wide text-dark-5 dark:text-dark-6">
          {label}
        </p>
        <p className="mt-0.5 text-sm font-medium text-dark dark:text-white break-words">{value}</p>
      </div>
    </div>
  );
}

function formatDate(isoString: string) {
  const date = new Date(isoString);
  const d = String(date.getDate()).padStart(2, "0");
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const y = date.getFullYear();
  return `${d}/${m}/${y}`;
}

export default function ViewContactPage() {
  usePageTitle("Detalle del Mensaje");
  const params = useParams();
  const router = useRouter();
  const id = parseInt(params?.id as string, 10);

  const [data, setData] = useState<ApiContact | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (isNaN(id)) return;
    let ignore = false;
    getContact(id)
      .then((res) => { if (!ignore) setData(res); })
      .catch((err) => { if (!ignore) alert.error("Error", getApiErrorMessage(err)); })
      .finally(() => { if (!ignore) setLoading(false); });
    return () => { ignore = true; };
  }, [id]);

  const handleDelete = async () => {
    if (!data) return;
    const ok = await alert.confirm({
      title: "¿Eliminar mensaje?",
      text: `Se eliminará el mensaje de ${data.name}. Esta acción no se puede deshacer.`,
      confirmButtonText: "Sí, eliminar",
      cancelButtonText: "Cancelar",
      onConfirm: async () => {
        await deleteContact(data.id);
      },
    });
    if (ok) {
      await alert.success("Eliminado", "Mensaje eliminado correctamente.");
      router.push("/4dnn1n/contacts");
    }
  };

  if (loading) {
    return (
      <div className="rounded-[10px] bg-white shadow-1 dark:bg-gray-dark dark:shadow-card">
        <div className="flex items-center justify-between gap-4 border-b border-stroke px-4 py-4 dark:border-dark-3 sm:px-6 xl:px-7.5">
          <div className="space-y-2">
            <div className="h-5 w-48 animate-pulse rounded bg-gray-200 dark:bg-dark-3" />
            <div className="h-4 w-64 animate-pulse rounded bg-gray-200 dark:bg-dark-3" />
          </div>
          <div className="h-9 w-24 animate-pulse rounded-lg bg-gray-200 dark:bg-dark-3" />
        </div>
        <div className="p-4 sm:p-6 xl:p-10">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="flex items-start gap-3 rounded-xl border border-stroke p-4 dark:border-dark-3">
                <div className="mt-0.5 h-5 w-5 shrink-0 animate-pulse rounded bg-gray-200 dark:bg-dark-3" />
                <div className="min-w-0 flex-1 space-y-2">
                  <div className="h-3 w-20 animate-pulse rounded bg-gray-200 dark:bg-dark-3" />
                  <div className="h-4 w-40 animate-pulse rounded bg-gray-200 dark:bg-dark-3" />
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="p-6 text-center text-red-500">
        No se pudo cargar el mensaje o no existe.
      </div>
    );
  }

  return (
    <ShowcaseSection
      title="Detalle del Mensaje"
      description={`Mensaje de contacto #${data.id}`}
      actions={
        <Link href="/4dnn1n/contacts">
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
      <div className="mx-auto max-w-3xl space-y-4">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <Field icon={<User className="h-4 w-4" />} label="Nombre" value={data.name} />
          <Field icon={<Mail className="h-4 w-4" />} label="Correo" value={data.email} />
          <Field icon={<Phone className="h-4 w-4" />} label="Teléfono" value={data.phone} />
          <Field
            icon={<MapPin className="h-4 w-4" />}
            label="Ciudad"
            value={data.city?.name ?? "-"}
          />
          <Field icon={<Tag className="h-4 w-4" />} label="Asunto" value={data.subject} />
          <Field
            icon={<Calendar className="h-4 w-4" />}
            label="Fecha de envío"
            value={formatDate(data.created_at)}
          />
        </div>

        <div className="flex items-start gap-3 rounded-xl border border-stroke bg-background p-4 dark:border-dark-3">
          <span className="mt-0.5 shrink-0 text-primary">
            <MessageSquare className="h-4 w-4" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-xs font-medium uppercase tracking-wide text-dark-5 dark:text-dark-6">
              Mensaje
            </p>
            <p className="mt-1 text-sm text-dark dark:text-white whitespace-pre-wrap leading-relaxed">
              {data.comment}
            </p>
          </div>
        </div>

        <div className="flex justify-end pt-2">
          <button
            type="button"
            onClick={handleDelete}
            className="inline-flex items-center gap-2 rounded-lg bg-red-500 px-4 py-2 text-sm font-medium text-white hover:bg-red-600 transition-colors"
          >
            Eliminar mensaje
          </button>
        </div>
      </div>
    </ShowcaseSection>
  );
}
```

---

## Task 10: Activar enlace en el sidebar

**Files:**
- Modify: `frontend-cm/src/components/Layouts/sidebar/data/index.ts`

- [ ] **Step 1: Actualizar la URL de "Contactos"** — cambiar `url: ""` por `url: "/4dnn1n/contacts"`

```ts
{
  title: "Contactos",
  icon: Phone,
  url: "/4dnn1n/contacts",
  items: [],
},
```

---

## Self-Review

**Cobertura del spec:**
- ✅ Migración: `address` eliminado, `subject` agregado — Task 1
- ✅ Modelo actualizado — Task 2
- ✅ `store` público con validaciones (`movil`, `asunto`, `mensaje`) — Task 3
- ✅ `index` paginado con búsqueda por `name`/`email` — Task 3
- ✅ `show` con `city:id,name` — Task 3
- ✅ `destroy` hard delete — Task 3
- ✅ Rutas públicas y admin registradas — Task 4
- ✅ Tests backend (store, validaciones, index, show, destroy) — Task 5
- ✅ `fetch.ts` con `getContacts`, `getContact`, `deleteContact` — Task 6
- ✅ Columnas: Nombre, Correo, Ciudad, Teléfono, **Asunto**, Mensaje truncado, Fecha, Acciones — Task 7
- ✅ Lista con `useServerTable` + confirmación de eliminación — Task 8
- ✅ Página detalle `/contacts/[id]` con todos los campos + botón eliminar — Task 9
- ✅ Sidebar actualizado — Task 10

**Consistencia de tipos:** `ApiContact` definido en Task 6, usado en Tasks 7, 8 y 9. Campos `subject` y `comment` consistentes en toda la cadena (DB → model → controller → fetch → UI).
