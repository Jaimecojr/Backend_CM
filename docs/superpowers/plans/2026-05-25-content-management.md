# Content Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Módulo de Administración de Contenido con dos secciones: Aliados Estratégicos (max 6, imagen + URL) y Especialistas de la Salud (max 4, foto + nombre), con landing en el panel y endpoints públicos para la página web.

**Architecture:** Backend Laravel con dos controladores independientes (`ContentAllyController`, `ContentSpecialistController`), tablas `content_allies` y `content_specialists`, y endpoints públicos sin auth. Frontend Next.js con landing page de dos cards y páginas CRUD separadas para cada sección.

**Tech Stack:** Laravel 11 (PHP), MySQL, Next.js 14 (App Router), TypeScript, TanStack Table, Tailwind CSS, Lucide React.

---

## Mapa de archivos

### Backend (api-cm)
| Acción | Archivo |
|---|---|
| Crear | `database/migrations/2026_05_25_000001_create_content_allies_table.php` |
| Crear | `database/migrations/2026_05_25_000002_create_content_specialists_table.php` |
| Crear | `app/Models/ContentAlly.php` |
| Crear | `app/Models/ContentSpecialist.php` |
| Crear | `app/Http/Controllers/ContentAllyController.php` |
| Crear | `app/Http/Controllers/ContentSpecialistController.php` |
| Modificar | `routes/api.php` |
| Crear | `tests/Feature/ContentAllyTest.php` |
| Crear | `tests/Feature/ContentSpecialistTest.php` |

### Frontend (frontend-cm)
| Acción | Archivo |
|---|---|
| Modificar | `src/components/Layouts/sidebar/data/index.ts` |
| Crear | `src/app/4dnn1n/content/page.tsx` |
| Crear | `src/app/4dnn1n/content/allies/fetch.ts` |
| Crear | `src/app/4dnn1n/content/allies/page.tsx` |
| Crear | `src/app/4dnn1n/content/allies/_components/columns.tsx` |
| Crear | `src/app/4dnn1n/content/allies/_components/AllyForm.tsx` |
| Crear | `src/app/4dnn1n/content/allies/new/page.tsx` |
| Crear | `src/app/4dnn1n/content/allies/[id]/edit/page.tsx` |
| Crear | `src/app/4dnn1n/content/specialists/fetch.ts` |
| Crear | `src/app/4dnn1n/content/specialists/page.tsx` |
| Crear | `src/app/4dnn1n/content/specialists/_components/columns.tsx` |
| Crear | `src/app/4dnn1n/content/specialists/_components/SpecialistForm.tsx` |
| Crear | `src/app/4dnn1n/content/specialists/new/page.tsx` |
| Crear | `src/app/4dnn1n/content/specialists/[id]/edit/page.tsx` |

---

## Task 1: Migración `content_allies`

**Files:**
- Create: `database/migrations/2026_05_25_000001_create_content_allies_table.php`

- [ ] **Step 1: Crear el archivo de migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_allies', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('image_filename');
            $table->string('url');
            $table->integer('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_allies');
    }
};
```

- [ ] **Step 2: Correr la migración**

```bash
php artisan migrate
```

Expected: `2026_05_25_000001_create_content_allies_table ........ DONE`

---

## Task 2: Migración `content_specialists`

**Files:**
- Create: `database/migrations/2026_05_25_000002_create_content_specialists_table.php`

- [ ] **Step 1: Crear el archivo de migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_specialists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('photo');
            $table->string('photo_filename');
            $table->integer('position');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_specialists');
    }
};
```

- [ ] **Step 2: Correr la migración**

```bash
php artisan migrate
```

Expected: `2026_05_25_000002_create_content_specialists_table ........ DONE`

---

## Task 3: Modelo `ContentAlly`

**Files:**
- Create: `app/Models/ContentAlly.php`

- [ ] **Step 1: Crear el modelo**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentAlly extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'image_filename',
        'url',
        'position',
    ];
}
```

---

## Task 4: Modelo `ContentSpecialist`

**Files:**
- Create: `app/Models/ContentSpecialist.php`

- [ ] **Step 1: Crear el modelo**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentSpecialist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'photo',
        'photo_filename',
        'position',
    ];
}
```

---

## Task 5: `ContentAllyController`

**Files:**
- Create: `app/Http/Controllers/ContentAllyController.php`

- [ ] **Step 1: Crear el controlador completo**

```php
<?php

namespace App\Http\Controllers;

use App\Models\ContentAlly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContentAllyController extends Controller
{
    public function index()
    {
        $allies = ContentAlly::orderBy('position')->get();

        return response()->json([
            'message' => 'Aliados obtenidos correctamente',
            'data'    => $allies,
        ]);
    }

    public function store(Request $request)
    {
        if (ContentAlly::count() >= 6) {
            return response()->json([
                'message' => 'No se pueden agregar más de 6 aliados estratégicos',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'image'    => 'required|image|mimes:jpeg,png,webp|max:2048',
            'url'      => 'required|string|url|max:255',
            'position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image');
        $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('content_allies', $filename, 'public');

        $ally = ContentAlly::create([
            'image'          => $path,
            'image_filename' => $filename,
            'url'            => $request->url,
            'position'       => $request->position,
        ]);

        return response()->json([
            'message' => 'Aliado creado correctamente',
            'data'    => $ally,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $ally = ContentAlly::find($id);

        if (!$ally) {
            return response()->json(['message' => 'Aliado no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'image'    => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'url'      => 'required|string|url|max:255',
            'position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($ally->image);
            $file = $request->file('image');
            $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('content_allies', $filename, 'public');
            $ally->image = $path;
            $ally->image_filename = $filename;
        }

        $ally->url      = $request->url;
        $ally->position = $request->position;
        $ally->save();

        return response()->json([
            'message' => 'Aliado actualizado correctamente',
            'data'    => $ally,
        ]);
    }

    public function destroy($id)
    {
        $ally = ContentAlly::find($id);

        if (!$ally) {
            return response()->json(['message' => 'Aliado no encontrado'], 404);
        }

        Storage::disk('public')->delete($ally->image);
        $ally->delete();

        return response()->json(['message' => 'Aliado eliminado correctamente']);
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items'            => 'required|array',
            'items.*.id'       => 'required|integer|exists:content_allies,id',
            'items.*.position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->items as $item) {
            ContentAlly::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }

    public function publicIndex()
    {
        $allies = ContentAlly::orderBy('position')
            ->get(['id', 'image', 'url', 'position']);

        $data = $allies->map(fn ($a) => [
            'id'        => $a->id,
            'image_url' => Storage::url($a->image),
            'url'       => $a->url,
            'position'  => $a->position,
        ]);

        return response()->json([
            'message' => 'Aliados obtenidos correctamente',
            'data'    => $data,
        ]);
    }
}
```

---

## Task 6: `ContentSpecialistController`

**Files:**
- Create: `app/Http/Controllers/ContentSpecialistController.php`

- [ ] **Step 1: Crear el controlador completo**

```php
<?php

namespace App\Http\Controllers;

use App\Models\ContentSpecialist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContentSpecialistController extends Controller
{
    public function index()
    {
        $specialists = ContentSpecialist::orderBy('position')->get();

        return response()->json([
            'message' => 'Especialistas obtenidos correctamente',
            'data'    => $specialists,
        ]);
    }

    public function store(Request $request)
    {
        if (ContentSpecialist::count() >= 4) {
            return response()->json([
                'message' => 'No se pueden agregar más de 4 especialistas',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'photo'    => 'required|image|mimes:jpeg,png,webp|max:2048',
            'position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $file = $request->file('photo');
        $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('content_specialists', $filename, 'public');

        $specialist = ContentSpecialist::create([
            'name'           => $request->name,
            'photo'          => $path,
            'photo_filename' => $filename,
            'position'       => $request->position,
        ]);

        return response()->json([
            'message' => 'Especialista creado correctamente',
            'data'    => $specialist,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $specialist = ContentSpecialist::find($id);

        if (!$specialist) {
            return response()->json(['message' => 'Especialista no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'photo'    => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->hasFile('photo')) {
            Storage::disk('public')->delete($specialist->photo);
            $file = $request->file('photo');
            $filename = time() . '_' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('content_specialists', $filename, 'public');
            $specialist->photo = $path;
            $specialist->photo_filename = $filename;
        }

        $specialist->name     = $request->name;
        $specialist->position = $request->position;
        $specialist->save();

        return response()->json([
            'message' => 'Especialista actualizado correctamente',
            'data'    => $specialist,
        ]);
    }

    public function destroy($id)
    {
        $specialist = ContentSpecialist::find($id);

        if (!$specialist) {
            return response()->json(['message' => 'Especialista no encontrado'], 404);
        }

        Storage::disk('public')->delete($specialist->photo);
        $specialist->delete();

        return response()->json(['message' => 'Especialista eliminado correctamente']);
    }

    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items'            => 'required|array',
            'items.*.id'       => 'required|integer|exists:content_specialists,id',
            'items.*.position' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->items as $item) {
            ContentSpecialist::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Orden actualizado correctamente']);
    }

    public function publicIndex()
    {
        $specialists = ContentSpecialist::orderBy('position')
            ->get(['id', 'name', 'photo', 'position']);

        $data = $specialists->map(fn ($s) => [
            'id'        => $s->id,
            'name'      => $s->name,
            'photo_url' => Storage::url($s->photo),
            'position'  => $s->position,
        ]);

        return response()->json([
            'message' => 'Especialistas obtenidos correctamente',
            'data'    => $data,
        ]);
    }
}
```

---

## Task 7: Rutas en `routes/api.php`

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Agregar imports al inicio del archivo**

Agregar después de la línea `use App\Http\Controllers\ContactController;`:

```php
use App\Http\Controllers\ContentAllyController;
use App\Http\Controllers\ContentSpecialistController;
```

- [ ] **Step 2: Agregar rutas públicas**

Dentro del bloque `Route::prefix('public')->group(function () { ... })`, agregar al final (antes del cierre `});`):

```php
    Route::get('content-allies', [ContentAllyController::class, 'publicIndex']);
    Route::get('content-specialists', [ContentSpecialistController::class, 'publicIndex']);
```

- [ ] **Step 3: Agregar rutas admin**

Dentro del bloque `Route::middleware('auth:sanctum')->group(function () { ... })`, agregar al final (antes del cierre `});`):

```php
    // Administración de contenido — Aliados estratégicos
    Route::put('content-allies/reorder', [ContentAllyController::class, 'reorder']);
    Route::apiResource('content-allies', ContentAllyController::class)->except(['show']);

    // Administración de contenido — Especialistas de la salud
    Route::put('content-specialists/reorder', [ContentSpecialistController::class, 'reorder']);
    Route::apiResource('content-specialists', ContentSpecialistController::class)->except(['show']);
```

- [ ] **Step 4: Verificar las rutas**

```bash
php artisan route:list --path=content
```

Expected output — deben aparecer estas rutas:
```
GET    api/public/content-allies
GET    api/public/content-specialists
GET    api/content-allies
POST   api/content-allies
PUT    api/content-allies/reorder
PUT    api/content-allies/{content_ally}
DELETE api/content-allies/{content_ally}
GET    api/content-specialists
POST   api/content-specialists
PUT    api/content-specialists/reorder
PUT    api/content-specialists/{content_specialist}
DELETE api/content-specialists/{content_specialist}
```

---

## Task 8: Tests `ContentAllyTest`

**Files:**
- Create: `tests/Feature/ContentAllyTest.php`

- [ ] **Step 1: Crear el archivo de tests**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentAlly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentAllyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['type' => 1, 'state' => 1]);
    }

    private function fakeImage(): UploadedFile
    {
        Storage::fake('public');
        return UploadedFile::fake()->image('banner.jpg');
    }

    // ── Endpoint público ──

    public function test_public_index_retorna_aliados_ordenados(): void
    {
        Storage::fake('public');
        ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 2]);
        ContentAlly::create(['image' => 'content_allies/b.jpg', 'image_filename' => 'b.jpg', 'url' => 'https://b.com', 'position' => 1]);

        $response = $this->getJson('/api/public/content-allies');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('data.0.url', 'https://b.com');
    }

    public function test_public_index_no_expone_image_filename(): void
    {
        Storage::fake('public');
        ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);

        $response = $this->getJson('/api/public/content-allies');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('image_filename', $response->json('data.0'));
    }

    // ── Endpoints admin ──

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/content-allies')->assertStatus(401);
    }

    public function test_index_retorna_todos_los_aliados(): void
    {
        Storage::fake('public');
        ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);
        ContentAlly::create(['image' => 'content_allies/b.jpg', 'image_filename' => 'b.jpg', 'url' => 'https://b.com', 'position' => 2]);

        $response = $this->actingAs($this->admin())->getJson('/api/content-allies');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_store_crea_aliado_con_imagen(): void
    {
        $image = $this->fakeImage();

        $response = $this->actingAs($this->admin())->postJson('/api/content-allies', [
            'image'    => $image,
            'url'      => 'https://empresa.com',
            'position' => 1,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.url', 'https://empresa.com')
                 ->assertJsonPath('data.position', 1);

        $this->assertDatabaseHas('content_allies', ['url' => 'https://empresa.com']);
    }

    public function test_store_rechaza_cuando_hay_6_aliados(): void
    {
        Storage::fake('public');
        for ($i = 1; $i <= 6; $i++) {
            ContentAlly::create(['image' => "content_allies/{$i}.jpg", 'image_filename' => "{$i}.jpg", 'url' => "https://a{$i}.com", 'position' => $i]);
        }

        $response = $this->actingAs($this->admin())->postJson('/api/content-allies', [
            'image'    => $this->fakeImage(),
            'url'      => 'https://extra.com',
            'position' => 7,
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', fn ($v) => str_contains($v, '6'));
    }

    public function test_store_requiere_url_valida(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/content-allies', [
            'image'    => $this->fakeImage(),
            'url'      => 'no-es-url',
            'position' => 1,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors' => ['url']]);
    }

    public function test_update_actualiza_url_y_posicion(): void
    {
        Storage::fake('public');
        $ally = ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://old.com', 'position' => 1]);

        $response = $this->actingAs($this->admin())->putJson("/api/content-allies/{$ally->id}", [
            'url'      => 'https://new.com',
            'position' => 2,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.url', 'https://new.com');
        $this->assertDatabaseHas('content_allies', ['id' => $ally->id, 'url' => 'https://new.com', 'position' => 2]);
    }

    public function test_update_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())
             ->putJson('/api/content-allies/99999', ['url' => 'https://x.com', 'position' => 1])
             ->assertStatus(404);
    }

    public function test_destroy_elimina_aliado(): void
    {
        Storage::fake('public');
        $ally = ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);

        $this->actingAs($this->admin())
             ->deleteJson("/api/content-allies/{$ally->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('content_allies', ['id' => $ally->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())
             ->deleteJson('/api/content-allies/99999')
             ->assertStatus(404);
    }

    public function test_reorder_actualiza_posiciones(): void
    {
        Storage::fake('public');
        $a = ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);
        $b = ContentAlly::create(['image' => 'content_allies/b.jpg', 'image_filename' => 'b.jpg', 'url' => 'https://b.com', 'position' => 2]);

        $response = $this->actingAs($this->admin())->putJson('/api/content-allies/reorder', [
            'items' => [
                ['id' => $a->id, 'position' => 2],
                ['id' => $b->id, 'position' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('content_allies', ['id' => $a->id, 'position' => 2]);
        $this->assertDatabaseHas('content_allies', ['id' => $b->id, 'position' => 1]);
    }
}
```

- [ ] **Step 2: Correr los tests**

```bash
php artisan test tests/Feature/ContentAllyTest.php
```

Expected: todos los tests en PASS.

- [ ] **Step 3: Commit backend aliados**

```bash
git add database/migrations/2026_05_25_000001_create_content_allies_table.php \
        app/Models/ContentAlly.php \
        app/Http/Controllers/ContentAllyController.php \
        tests/Feature/ContentAllyTest.php
git commit -m "feat: módulo content allies — migración, modelo, controlador y tests"
```

---

## Task 9: Tests `ContentSpecialistTest`

**Files:**
- Create: `tests/Feature/ContentSpecialistTest.php`

- [ ] **Step 1: Crear el archivo de tests**

```php
<?php

namespace Tests\Feature;

use App\Models\ContentSpecialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentSpecialistTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['type' => 1, 'state' => 1]);
    }

    private function fakePhoto(): UploadedFile
    {
        Storage::fake('public');
        return UploadedFile::fake()->image('doctor.jpg');
    }

    // ── Endpoint público ──

    public function test_public_index_retorna_especialistas_ordenados(): void
    {
        Storage::fake('public');
        ContentSpecialist::create(['name' => 'Dr. B', 'photo' => 'content_specialists/b.jpg', 'photo_filename' => 'b.jpg', 'position' => 2]);
        ContentSpecialist::create(['name' => 'Dr. A', 'photo' => 'content_specialists/a.jpg', 'photo_filename' => 'a.jpg', 'position' => 1]);

        $response = $this->getJson('/api/public/content-specialists');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('data.0.name', 'Dr. A');
    }

    public function test_public_index_no_expone_photo_filename(): void
    {
        Storage::fake('public');
        ContentSpecialist::create(['name' => 'Dr. X', 'photo' => 'content_specialists/x.jpg', 'photo_filename' => 'x.jpg', 'position' => 1]);

        $response = $this->getJson('/api/public/content-specialists');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('photo_filename', $response->json('data.0'));
    }

    // ── Endpoints admin ──

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/content-specialists')->assertStatus(401);
    }

    public function test_store_crea_especialista_con_foto(): void
    {
        $photo = $this->fakePhoto();

        $response = $this->actingAs($this->admin())->postJson('/api/content-specialists', [
            'name'     => 'Dr. Juan Pérez',
            'photo'    => $photo,
            'position' => 1,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Dr. Juan Pérez');

        $this->assertDatabaseHas('content_specialists', ['name' => 'Dr. Juan Pérez']);
    }

    public function test_store_rechaza_cuando_hay_4_especialistas(): void
    {
        Storage::fake('public');
        for ($i = 1; $i <= 4; $i++) {
            ContentSpecialist::create(['name' => "Dr. {$i}", 'photo' => "content_specialists/{$i}.jpg", 'photo_filename' => "{$i}.jpg", 'position' => $i]);
        }

        $response = $this->actingAs($this->admin())->postJson('/api/content-specialists', [
            'name'     => 'Dr. Extra',
            'photo'    => $this->fakePhoto(),
            'position' => 5,
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', fn ($v) => str_contains($v, '4'));
    }

    public function test_update_actualiza_nombre_y_posicion(): void
    {
        Storage::fake('public');
        $s = ContentSpecialist::create(['name' => 'Dr. Viejo', 'photo' => 'content_specialists/v.jpg', 'photo_filename' => 'v.jpg', 'position' => 1]);

        $response = $this->actingAs($this->admin())->putJson("/api/content-specialists/{$s->id}", [
            'name'     => 'Dr. Nuevo',
            'position' => 2,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Dr. Nuevo');
    }

    public function test_destroy_elimina_especialista(): void
    {
        Storage::fake('public');
        $s = ContentSpecialist::create(['name' => 'Dr. X', 'photo' => 'content_specialists/x.jpg', 'photo_filename' => 'x.jpg', 'position' => 1]);

        $this->actingAs($this->admin())
             ->deleteJson("/api/content-specialists/{$s->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('content_specialists', ['id' => $s->id]);
    }

    public function test_reorder_actualiza_posiciones(): void
    {
        Storage::fake('public');
        $a = ContentSpecialist::create(['name' => 'Dr. A', 'photo' => 'content_specialists/a.jpg', 'photo_filename' => 'a.jpg', 'position' => 1]);
        $b = ContentSpecialist::create(['name' => 'Dr. B', 'photo' => 'content_specialists/b.jpg', 'photo_filename' => 'b.jpg', 'position' => 2]);

        $this->actingAs($this->admin())->putJson('/api/content-specialists/reorder', [
            'items' => [
                ['id' => $a->id, 'position' => 2],
                ['id' => $b->id, 'position' => 1],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('content_specialists', ['id' => $a->id, 'position' => 2]);
    }
}
```

- [ ] **Step 2: Correr los tests**

```bash
php artisan test tests/Feature/ContentSpecialistTest.php
```

Expected: todos los tests en PASS.

- [ ] **Step 3: Correr todos los tests**

```bash
php artisan test
```

Expected: todos los tests existentes siguen en PASS.

- [ ] **Step 4: Commit backend especialistas y rutas**

```bash
git add database/migrations/2026_05_25_000002_create_content_specialists_table.php \
        app/Models/ContentSpecialist.php \
        app/Http/Controllers/ContentSpecialistController.php \
        routes/api.php \
        tests/Feature/ContentSpecialistTest.php
git commit -m "feat: módulo content specialists — migración, modelo, controlador, rutas y tests"
```

---

## Task 10: Sidebar — URL para Administración de Contenido

**Files:**
- Modify: `src/components/Layouts/sidebar/data/index.ts`

- [ ] **Step 1: Actualizar la URL del ítem existente**

Buscar el objeto con `title: "Administración de contenido"` y cambiar `url: ""` por `url: "/4dnn1n/content"`:

```ts
{
  title: "Administración de contenido",
  icon: LayoutDashboard,
  url: "/4dnn1n/content",
  items: [],
},
```

---

## Task 11: Landing page `/4dnn1n/content`

**Files:**
- Create: `src/app/4dnn1n/content/page.tsx`

- [ ] **Step 1: Crear la página con dos cards**

```tsx
"use client";

import Link from "next/link";
import { Handshake, Stethoscope } from "lucide-react";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useAuth } from "@/context/AuthContext";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function ContentPage() {
  usePageTitle("Administración de Contenido");
  const { user } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (user && user.type !== 1) {
      router.replace("/4dnn1n/home");
    }
  }, [user, router]);

  if (!user || user.type !== 1) return null;

  return (
    <div className="mx-auto max-w-2xl">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-dark dark:text-white">
          Administración de Contenido
        </h1>
        <p className="mt-1 text-sm text-dark-5 dark:text-dark-6">
          Gestiona el contenido visible en la página web pública.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <Link
          href="/4dnn1n/content/allies"
          className="flex flex-col gap-4 rounded-2xl border border-stroke bg-white p-6 shadow-sm transition hover:shadow-md dark:border-dark-3 dark:bg-gray-dark"
        >
          <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
            <Handshake className="h-6 w-6 text-primary" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-dark dark:text-white">
              Aliados Estratégicos
            </h2>
            <p className="mt-1 text-sm text-dark-5 dark:text-dark-6">
              Banners de empresas aliadas que aparecen en la página web. Máximo 6.
            </p>
          </div>
          <span className="mt-auto inline-flex items-center text-sm font-medium text-primary">
            Gestionar →
          </span>
        </Link>

        <Link
          href="/4dnn1n/content/specialists"
          className="flex flex-col gap-4 rounded-2xl border border-stroke bg-white p-6 shadow-sm transition hover:shadow-md dark:border-dark-3 dark:bg-gray-dark"
        >
          <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
            <Stethoscope className="h-6 w-6 text-primary" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-dark dark:text-white">
              Especialistas de la Salud
            </h2>
            <p className="mt-1 text-sm text-dark-5 dark:text-dark-6">
              Médicos destacados en el cuadro médico del homepage. Máximo 4.
            </p>
          </div>
          <span className="mt-auto inline-flex items-center text-sm font-medium text-primary">
            Gestionar →
          </span>
        </Link>
      </div>
    </div>
  );
}
```

---

## Task 12: `fetch.ts` para Aliados

**Files:**
- Create: `src/app/4dnn1n/content/allies/fetch.ts`

- [ ] **Step 1: Crear el archivo**

```ts
import { apiFetch, csrf, getXsrfToken } from "@/lib/api";
import { memCache, TTL_CATALOG } from "@/lib/memCache";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

export type ApiAlly = {
  id: number;
  image: string;
  image_filename: string;
  url: string;
  position: number;
  created_at?: string;
  updated_at?: string;
};

type ApiResponse<T> = { message: string; data: T };

async function apiFetchFormData<T = any>(
  path: string,
  method: string,
  formData: FormData,
): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    method,
    credentials: "include",
    body: formData,
    headers: {
      Accept: "application/json",
      "X-XSRF-TOKEN": getXsrfToken() ?? "",
    },
  });
  const data = (await res.json().catch(() => ({}))) as any;
  if (!res.ok) {
    throw new Error(data?.message || `Error ${res.status}`);
  }
  return data as T;
}

export async function getAllies(): Promise<ApiAlly[]> {
  return memCache.get("content-allies:all", TTL_CATALOG, async () => {
    const res = await apiFetch<ApiResponse<ApiAlly[]>>("/api/content-allies");
    return res.data ?? [];
  });
}

export async function createAlly(formData: FormData): Promise<ApiAlly> {
  await csrf();
  const res = await apiFetchFormData<ApiResponse<ApiAlly>>(
    "/api/content-allies",
    "POST",
    formData,
  );
  memCache.invalidatePrefix("content-allies:");
  return res.data;
}

export async function updateAlly(id: number, formData: FormData): Promise<ApiAlly> {
  await csrf();
  formData.append("_method", "PUT");
  const res = await apiFetchFormData<ApiResponse<ApiAlly>>(
    `/api/content-allies/${id}`,
    "POST",
    formData,
  );
  memCache.invalidatePrefix("content-allies:");
  return res.data;
}

export async function deleteAlly(id: number): Promise<void> {
  await csrf();
  await apiFetch(`/api/content-allies/${id}`, { method: "DELETE" });
  memCache.invalidatePrefix("content-allies:");
}

export async function reorderAllies(
  items: { id: number; position: number }[],
): Promise<void> {
  await csrf();
  await apiFetch("/api/content-allies/reorder", {
    method: "PUT",
    body: JSON.stringify({ items }),
  });
  memCache.invalidatePrefix("content-allies:");
}
```

---

## Task 13: Lista de Aliados — página y columnas

**Files:**
- Create: `src/app/4dnn1n/content/allies/page.tsx`
- Create: `src/app/4dnn1n/content/allies/_components/columns.tsx`

- [ ] **Step 1: Crear columnas**

`src/app/4dnn1n/content/allies/_components/columns.tsx`:

```tsx
"use client";

import type { ColumnDef } from "@tanstack/react-table";
import type { ApiAlly } from "../fetch";
import { Pencil, Trash2 } from "lucide-react";
import Link from "next/link";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

export function buildAllyColumns({
  onDelete,
}: {
  onDelete: (ally: ApiAlly) => void;
}): ColumnDef<ApiAlly>[] {
  return [
    {
      accessorKey: "position",
      header: "Pos.",
      cell: ({ row }) => (
        <div className="text-center font-medium">{row.original.position}</div>
      ),
    },
    {
      id: "image",
      header: "Imagen",
      cell: ({ row }) => {
        const src = `${API_URL}/storage/${row.original.image}`;
        return (
          <div className="flex items-center">
            <img
              src={src}
              alt="banner aliado"
              className="h-12 w-24 rounded-md object-cover border border-stroke"
            />
          </div>
        );
      },
    },
    {
      accessorKey: "url",
      header: "URL",
      cell: ({ row }) => (
        <a
          href={row.original.url}
          target="_blank"
          rel="noopener noreferrer"
          className="text-primary underline text-sm max-w-[200px] inline-block truncate"
          title={row.original.url}
        >
          {row.original.url}
        </a>
      ),
    },
    {
      id: "actions",
      header: () => <div className="text-center">Acciones</div>,
      cell: ({ row }) => {
        const ally = row.original;
        return (
          <div className="grid w-fit grid-cols-2 place-items-center gap-1 mx-auto">
            <Link
              href={`/4dnn1n/content/allies/${ally.id}/edit`}
              className="hover:bg-muted rounded-md p-2"
              title="Editar"
              aria-label="Editar aliado"
            >
              <Pencil className="h-4 w-4 text-primary" />
            </Link>
            <button
              type="button"
              onClick={() => onDelete(ally)}
              className="hover:bg-muted rounded-md p-2"
              title="Eliminar"
              aria-label="Eliminar aliado"
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

- [ ] **Step 2: Crear la página de lista**

`src/app/4dnn1n/content/allies/page.tsx`:

```tsx
"use client";

import { useMemo } from "react";
import Link from "next/link";
import { DataTable } from "@/components/data-table/DataTable";
import { LoadingOverlay } from "@/components/LoadingOverlay";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useClientTable } from "@/hooks/useClientTable";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import { Button } from "@/components/ui-elements/button";
import { Plus } from "lucide-react";

import { getAllies, deleteAlly, type ApiAlly } from "./fetch";
import { buildAllyColumns } from "./_components/columns";

export default function AlliesPage() {
  usePageTitle("Aliados Estratégicos");

  const { data, setData, loading } = useClientTable(getAllies);

  const onDelete = async (ally: ApiAlly) => {
    try {
      const ok = await alert.confirm({
        title: "¿Eliminar aliado?",
        text: "Se eliminará el banner y no se puede deshacer.",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        onConfirm: async () => {
          setData((prev) => prev.filter((x) => x.id !== ally.id));
          await deleteAlly(ally.id);
        },
      });
      if (ok) {
        await alert.success("Eliminado", "Aliado eliminado correctamente.");
      }
    } catch (err) {
      setData((prev) => [...prev, ally]);
      await alert.error("Error", getApiErrorMessage(err));
    }
  };

  const columns = useMemo(
    () => buildAllyColumns({ onDelete }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  const atLimit = data.length >= 6;

  return (
    <>
      <LoadingOverlay isLoading={loading} />
      <DataTable
        title="Aliados Estratégicos"
        columns={columns}
        data={data}
        defaultPageSize={10}
        enableStateFilter={false}
        hideSearch
        toolbarActions={
          <Link href={atLimit ? "#" : "/4dnn1n/content/allies/new"}>
            <Button
              type="button"
              disabled={atLimit}
              title={atLimit ? "Límite de 6 aliados alcanzado" : undefined}
              className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-medium text-gray-2 hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Plus className="h-4 w-4" />
              Agregar aliado
            </Button>
          </Link>
        }
      />
    </>
  );
}
```

---

## Task 14: `AllyForm` — componente de formulario

**Files:**
- Create: `src/app/4dnn1n/content/allies/_components/AllyForm.tsx`

- [ ] **Step 1: Crear el componente**

```tsx
"use client";

import { useState, useRef } from "react";
import { Save, Eraser, ImagePlus } from "lucide-react";
import type { ApiAlly } from "../fetch";
import { Button } from "@/components/ui-elements/button";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

type Mode = "create" | "edit";

type Props = {
  mode: Mode;
  initial?: ApiAlly;
  onSubmit: (formData: FormData) => Promise<void>;
};

function Label({ children, required }: { children: React.ReactNode; required?: boolean }) {
  return (
    <label className="text-sm font-medium">
      {children} {required ? <span className="text-red-500">*</span> : null}
    </label>
  );
}

export default function AllyForm({ mode, initial, onSubmit }: Props) {
  const [url, setUrl] = useState(initial?.url ?? "");
  const [position, setPosition] = useState(String(initial?.position ?? "1"));
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [previewSrc, setPreviewSrc] = useState<string | null>(
    initial?.image ? `${API_URL}/storage/${initial.image}` : null,
  );
  const [saving, setSaving] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setImageFile(file);
    setPreviewSrc(URL.createObjectURL(file));
  };

  const clear = () => {
    setUrl("");
    setPosition("1");
    setImageFile(null);
    setPreviewSrc(null);
    if (inputRef.current) inputRef.current.value = "";
  };

  const canSubmit = (() => {
    if (!url) return false;
    if (mode === "create" && !imageFile) return false;
    if (!position || Number(position) < 1) return false;
    return true;
  })();

  const submit = async () => {
    if (!canSubmit) return;
    const fd = new FormData();
    if (imageFile) fd.append("image", imageFile);
    fd.append("url", url);
    fd.append("position", position);
    setSaving(true);
    try {
      await onSubmit(fd);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="bg-background rounded-2xl border border-stroke p-5 shadow-sm dark:border-dark-3">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">

        <div className="md:col-span-2">
          <Label required={mode === "create"}>
            {mode === "create" ? "Imagen del banner" : "Imagen del banner (dejar vacío para conservar la actual)"}
          </Label>
          <div
            className="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-stroke p-6 hover:border-primary dark:border-dark-3"
            onClick={() => inputRef.current?.click()}
          >
            {previewSrc ? (
              <img
                src={previewSrc}
                alt="Preview"
                className="max-h-40 rounded-md object-contain"
              />
            ) : (
              <div className="flex flex-col items-center gap-2 text-dark-5">
                <ImagePlus className="h-8 w-8" />
                <span className="text-sm">Haz clic para seleccionar imagen</span>
                <span className="text-xs">JPEG, PNG o WebP — máx. 2MB</span>
              </div>
            )}
            <input
              ref={inputRef}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              className="hidden"
              onChange={handleImageChange}
            />
          </div>
        </div>

        <div className="md:col-span-2">
          <Label required>URL del aliado</Label>
          <input
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder="https://empresa.com"
            className="mt-1 w-full rounded-lg border px-3 py-2"
          />
        </div>

        <div>
          <Label required>Posición</Label>
          <input
            value={position}
            onChange={(e) => setPosition(e.target.value.replace(/\D/g, ""))}
            inputMode="numeric"
            placeholder="1"
            className="mt-1 w-full rounded-lg border px-3 py-2"
          />
        </div>

      </div>

      <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
        <Button
          type="button"
          onClick={clear}
          disabled={saving}
          className="inline-flex items-center gap-2 rounded-lg border border-stroke px-4 py-2 font-medium text-dark hover:shadow-1 dark:border-dark-3 dark:text-white"
        >
          <Eraser className="h-4 w-4" />
          Limpiar
        </Button>
        <Button
          type="button"
          onClick={submit}
          disabled={!canSubmit || saving}
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-medium text-gray-2 hover:bg-opacity-90 disabled:opacity-50"
        >
          <Save className="h-4 w-4" />
          {saving ? "Guardando..." : "Guardar"}
        </Button>
      </div>
    </div>
  );
}
```

---

## Task 15: Páginas Nuevo / Editar Aliado

**Files:**
- Create: `src/app/4dnn1n/content/allies/new/page.tsx`
- Create: `src/app/4dnn1n/content/allies/[id]/edit/page.tsx`

- [ ] **Step 1: Crear página de nuevo aliado**

`src/app/4dnn1n/content/allies/new/page.tsx`:

```tsx
"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useAuth } from "@/context/AuthContext";
import { useEffect } from "react";
import { ShowcaseSection } from "@/components/Layouts/showcase-section";
import { Button } from "@/components/ui-elements/button";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import AllyForm from "../_components/AllyForm";
import { createAlly } from "../fetch";

export default function NewAllyPage() {
  usePageTitle("Agregar Aliado Estratégico");
  const router = useRouter();
  const { user } = useAuth();

  useEffect(() => {
    if (user && user.type !== 1) router.replace("/4dnn1n/content");
  }, [user, router]);

  if (!user || user.type !== 1) return null;

  return (
    <ShowcaseSection
      title="Agregar Aliado Estratégico"
      description="Sube el banner y la URL del nuevo aliado"
      actions={
        <Link href="/4dnn1n/content/allies">
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
      <AllyForm
        mode="create"
        onSubmit={async (formData) => {
          try {
            const ok = await alert.confirm({
              title: "¿Agregar aliado?",
              text: "Se guardará el banner en el sistema.",
              confirmButtonText: "Sí, agregar",
              cancelButtonText: "Cancelar",
              onConfirm: () => createAlly(formData),
            });
            if (ok) {
              await alert.success("Creado", "Aliado agregado exitosamente.");
              router.push("/4dnn1n/content/allies");
            }
          } catch (err) {
            await alert.error("Error", getApiErrorMessage(err));
          }
        }}
      />
    </ShowcaseSection>
  );
}
```

- [ ] **Step 2: Crear página de edición de aliado**

`src/app/4dnn1n/content/allies/[id]/edit/page.tsx`:

```tsx
"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useParams } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useAuth } from "@/context/AuthContext";
import { ShowcaseSection } from "@/components/Layouts/showcase-section";
import { Button } from "@/components/ui-elements/button";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import AllyForm from "../../_components/AllyForm";
import { getAllies, updateAlly, type ApiAlly } from "../../fetch";

export default function EditAllyPage() {
  usePageTitle("Editar Aliado Estratégico");
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const [ally, setAlly] = useState<ApiAlly | null>(null);

  useEffect(() => {
    if (user && user.type !== 1) router.replace("/4dnn1n/content");
  }, [user, router]);

  useEffect(() => {
    getAllies().then((list) => {
      const found = list.find((a) => String(a.id) === String(id));
      if (!found) router.replace("/4dnn1n/content/allies");
      else setAlly(found);
    });
  }, [id, router]);

  if (!user || user.type !== 1 || !ally) return null;

  return (
    <ShowcaseSection
      title="Editar Aliado Estratégico"
      description="Modifica la información del aliado"
      actions={
        <Link href="/4dnn1n/content/allies">
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
      <AllyForm
        mode="edit"
        initial={ally}
        onSubmit={async (formData) => {
          try {
            const ok = await alert.confirm({
              title: "¿Actualizar aliado?",
              text: "Se guardarán los cambios.",
              confirmButtonText: "Sí, actualizar",
              cancelButtonText: "Cancelar",
              onConfirm: () => updateAlly(ally.id, formData),
            });
            if (ok) {
              await alert.success("Actualizado", "Aliado actualizado correctamente.");
              router.push("/4dnn1n/content/allies");
            }
          } catch (err) {
            await alert.error("Error", getApiErrorMessage(err));
          }
        }}
      />
    </ShowcaseSection>
  );
}
```

---

## Task 16: `fetch.ts` para Especialistas

**Files:**
- Create: `src/app/4dnn1n/content/specialists/fetch.ts`

- [ ] **Step 1: Crear el archivo**

```ts
import { apiFetch, csrf, getXsrfToken } from "@/lib/api";
import { memCache, TTL_CATALOG } from "@/lib/memCache";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

export type ApiSpecialist = {
  id: number;
  name: string;
  photo: string;
  photo_filename: string;
  position: number;
  created_at?: string;
  updated_at?: string;
};

type ApiResponse<T> = { message: string; data: T };

async function apiFetchFormData<T = any>(
  path: string,
  method: string,
  formData: FormData,
): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    method,
    credentials: "include",
    body: formData,
    headers: {
      Accept: "application/json",
      "X-XSRF-TOKEN": getXsrfToken() ?? "",
    },
  });
  const data = (await res.json().catch(() => ({}))) as any;
  if (!res.ok) {
    throw new Error(data?.message || `Error ${res.status}`);
  }
  return data as T;
}

export async function getSpecialists(): Promise<ApiSpecialist[]> {
  return memCache.get("content-specialists:all", TTL_CATALOG, async () => {
    const res = await apiFetch<ApiResponse<ApiSpecialist[]>>("/api/content-specialists");
    return res.data ?? [];
  });
}

export async function createSpecialist(formData: FormData): Promise<ApiSpecialist> {
  await csrf();
  const res = await apiFetchFormData<ApiResponse<ApiSpecialist>>(
    "/api/content-specialists",
    "POST",
    formData,
  );
  memCache.invalidatePrefix("content-specialists:");
  return res.data;
}

export async function updateSpecialist(id: number, formData: FormData): Promise<ApiSpecialist> {
  await csrf();
  formData.append("_method", "PUT");
  const res = await apiFetchFormData<ApiResponse<ApiSpecialist>>(
    `/api/content-specialists/${id}`,
    "POST",
    formData,
  );
  memCache.invalidatePrefix("content-specialists:");
  return res.data;
}

export async function deleteSpecialist(id: number): Promise<void> {
  await csrf();
  await apiFetch(`/api/content-specialists/${id}`, { method: "DELETE" });
  memCache.invalidatePrefix("content-specialists:");
}

export async function reorderSpecialists(
  items: { id: number; position: number }[],
): Promise<void> {
  await csrf();
  await apiFetch("/api/content-specialists/reorder", {
    method: "PUT",
    body: JSON.stringify({ items }),
  });
  memCache.invalidatePrefix("content-specialists:");
}
```

---

## Task 17: Lista de Especialistas — página y columnas

**Files:**
- Create: `src/app/4dnn1n/content/specialists/_components/columns.tsx`
- Create: `src/app/4dnn1n/content/specialists/page.tsx`

- [ ] **Step 1: Crear columnas**

`src/app/4dnn1n/content/specialists/_components/columns.tsx`:

```tsx
"use client";

import type { ColumnDef } from "@tanstack/react-table";
import type { ApiSpecialist } from "../fetch";
import { Pencil, Trash2 } from "lucide-react";
import Link from "next/link";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

export function buildSpecialistColumns({
  onDelete,
}: {
  onDelete: (specialist: ApiSpecialist) => void;
}): ColumnDef<ApiSpecialist>[] {
  return [
    {
      accessorKey: "position",
      header: "Pos.",
      cell: ({ row }) => (
        <div className="text-center font-medium">{row.original.position}</div>
      ),
    },
    {
      id: "photo",
      header: "Foto",
      cell: ({ row }) => {
        const src = `${API_URL}/storage/${row.original.photo}`;
        return (
          <div className="flex items-center">
            <img
              src={src}
              alt={row.original.name}
              className="h-14 w-14 rounded-full object-cover border border-stroke"
            />
          </div>
        );
      },
    },
    {
      accessorKey: "name",
      header: "Nombre",
      cell: ({ row }) => (
        <div className="font-medium">{row.original.name}</div>
      ),
    },
    {
      id: "actions",
      header: () => <div className="text-center">Acciones</div>,
      cell: ({ row }) => {
        const specialist = row.original;
        return (
          <div className="grid w-fit grid-cols-2 place-items-center gap-1 mx-auto">
            <Link
              href={`/4dnn1n/content/specialists/${specialist.id}/edit`}
              className="hover:bg-muted rounded-md p-2"
              title="Editar"
              aria-label="Editar especialista"
            >
              <Pencil className="h-4 w-4 text-primary" />
            </Link>
            <button
              type="button"
              onClick={() => onDelete(specialist)}
              className="hover:bg-muted rounded-md p-2"
              title="Eliminar"
              aria-label="Eliminar especialista"
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

- [ ] **Step 2: Crear la página de lista**

`src/app/4dnn1n/content/specialists/page.tsx`:

```tsx
"use client";

import { useMemo } from "react";
import Link from "next/link";
import { DataTable } from "@/components/data-table/DataTable";
import { LoadingOverlay } from "@/components/LoadingOverlay";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useClientTable } from "@/hooks/useClientTable";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import { Button } from "@/components/ui-elements/button";
import { Plus } from "lucide-react";

import { getSpecialists, deleteSpecialist, type ApiSpecialist } from "./fetch";
import { buildSpecialistColumns } from "./_components/columns";

export default function SpecialistsPage() {
  usePageTitle("Especialistas de la Salud");

  const { data, setData, loading } = useClientTable(getSpecialists);

  const onDelete = async (specialist: ApiSpecialist) => {
    try {
      const ok = await alert.confirm({
        title: "¿Eliminar especialista?",
        text: `Se eliminará a ${specialist.name}. Esta acción no se puede deshacer.`,
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar",
        onConfirm: async () => {
          setData((prev) => prev.filter((x) => x.id !== specialist.id));
          await deleteSpecialist(specialist.id);
        },
      });
      if (ok) {
        await alert.success("Eliminado", "Especialista eliminado correctamente.");
      }
    } catch (err) {
      setData((prev) => [...prev, specialist]);
      await alert.error("Error", getApiErrorMessage(err));
    }
  };

  const columns = useMemo(
    () => buildSpecialistColumns({ onDelete }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [],
  );

  const atLimit = data.length >= 4;

  return (
    <>
      <LoadingOverlay isLoading={loading} />
      <DataTable
        title="Especialistas de la Salud"
        columns={columns}
        data={data}
        defaultPageSize={10}
        enableStateFilter={false}
        hideSearch
        toolbarActions={
          <Link href={atLimit ? "#" : "/4dnn1n/content/specialists/new"}>
            <Button
              type="button"
              disabled={atLimit}
              title={atLimit ? "Límite de 4 especialistas alcanzado" : undefined}
              className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-medium text-gray-2 hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Plus className="h-4 w-4" />
              Agregar especialista
            </Button>
          </Link>
        }
      />
    </>
  );
}
```

---

## Task 18: `SpecialistForm` + páginas nuevo / editar

**Files:**
- Create: `src/app/4dnn1n/content/specialists/_components/SpecialistForm.tsx`
- Create: `src/app/4dnn1n/content/specialists/new/page.tsx`
- Create: `src/app/4dnn1n/content/specialists/[id]/edit/page.tsx`

- [ ] **Step 1: Crear el formulario de especialista**

`src/app/4dnn1n/content/specialists/_components/SpecialistForm.tsx`:

```tsx
"use client";

import { useState, useRef } from "react";
import { Save, Eraser, ImagePlus } from "lucide-react";
import type { ApiSpecialist } from "../fetch";
import { Button } from "@/components/ui-elements/button";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

type Mode = "create" | "edit";

type Props = {
  mode: Mode;
  initial?: ApiSpecialist;
  onSubmit: (formData: FormData) => Promise<void>;
};

function Label({ children, required }: { children: React.ReactNode; required?: boolean }) {
  return (
    <label className="text-sm font-medium">
      {children} {required ? <span className="text-red-500">*</span> : null}
    </label>
  );
}

export default function SpecialistForm({ mode, initial, onSubmit }: Props) {
  const [name, setName] = useState(initial?.name ?? "");
  const [position, setPosition] = useState(String(initial?.position ?? "1"));
  const [photoFile, setPhotoFile] = useState<File | null>(null);
  const [previewSrc, setPreviewSrc] = useState<string | null>(
    initial?.photo ? `${API_URL}/storage/${initial.photo}` : null,
  );
  const [saving, setSaving] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const handlePhotoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    setPhotoFile(file);
    setPreviewSrc(URL.createObjectURL(file));
  };

  const clear = () => {
    setName("");
    setPosition("1");
    setPhotoFile(null);
    setPreviewSrc(null);
    if (inputRef.current) inputRef.current.value = "";
  };

  const canSubmit = (() => {
    if (!name.trim()) return false;
    if (mode === "create" && !photoFile) return false;
    if (!position || Number(position) < 1) return false;
    return true;
  })();

  const submit = async () => {
    if (!canSubmit) return;
    const fd = new FormData();
    fd.append("name", name);
    if (photoFile) fd.append("photo", photoFile);
    fd.append("position", position);
    setSaving(true);
    try {
      await onSubmit(fd);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="bg-background rounded-2xl border border-stroke p-5 shadow-sm dark:border-dark-3">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">

        <div className="flex flex-col items-center gap-3">
          <Label required={mode === "create"}>
            {mode === "create" ? "Foto del médico" : "Foto (dejar vacío para conservar la actual)"}
          </Label>
          <div
            className="flex h-36 w-36 cursor-pointer items-center justify-center overflow-hidden rounded-full border-2 border-dashed border-stroke hover:border-primary dark:border-dark-3"
            onClick={() => inputRef.current?.click()}
          >
            {previewSrc ? (
              <img src={previewSrc} alt="Preview" className="h-full w-full object-cover" />
            ) : (
              <div className="flex flex-col items-center gap-1 text-dark-5">
                <ImagePlus className="h-6 w-6" />
                <span className="text-xs text-center px-2">Seleccionar foto</span>
              </div>
            )}
          </div>
          <span className="text-xs text-dark-5">JPEG, PNG o WebP — máx. 2MB</span>
          <input
            ref={inputRef}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="hidden"
            onChange={handlePhotoChange}
          />
        </div>

        <div className="flex flex-col gap-4 justify-center">
          <div>
            <Label required>Nombre completo</Label>
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Dr. Juan Pérez"
              className="mt-1 w-full rounded-lg border px-3 py-2"
            />
          </div>

          <div>
            <Label required>Posición</Label>
            <input
              value={position}
              onChange={(e) => setPosition(e.target.value.replace(/\D/g, ""))}
              inputMode="numeric"
              placeholder="1"
              className="mt-1 w-full rounded-lg border px-3 py-2"
            />
          </div>
        </div>

      </div>

      <div className="mt-6 flex flex-wrap items-center justify-end gap-3">
        <Button
          type="button"
          onClick={clear}
          disabled={saving}
          className="inline-flex items-center gap-2 rounded-lg border border-stroke px-4 py-2 font-medium text-dark hover:shadow-1 dark:border-dark-3 dark:text-white"
        >
          <Eraser className="h-4 w-4" />
          Limpiar
        </Button>
        <Button
          type="button"
          onClick={submit}
          disabled={!canSubmit || saving}
          className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-medium text-gray-2 hover:bg-opacity-90 disabled:opacity-50"
        >
          <Save className="h-4 w-4" />
          {saving ? "Guardando..." : "Guardar"}
        </Button>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Crear página de nuevo especialista**

`src/app/4dnn1n/content/specialists/new/page.tsx`:

```tsx
"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { useEffect } from "react";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useAuth } from "@/context/AuthContext";
import { ShowcaseSection } from "@/components/Layouts/showcase-section";
import { Button } from "@/components/ui-elements/button";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import SpecialistForm from "../_components/SpecialistForm";
import { createSpecialist } from "../fetch";

export default function NewSpecialistPage() {
  usePageTitle("Agregar Especialista");
  const router = useRouter();
  const { user } = useAuth();

  useEffect(() => {
    if (user && user.type !== 1) router.replace("/4dnn1n/content");
  }, [user, router]);

  if (!user || user.type !== 1) return null;

  return (
    <ShowcaseSection
      title="Agregar Especialista de la Salud"
      description="Sube la foto y el nombre del médico para el cuadro médico"
      actions={
        <Link href="/4dnn1n/content/specialists">
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
      <SpecialistForm
        mode="create"
        onSubmit={async (formData) => {
          try {
            const ok = await alert.confirm({
              title: "¿Agregar especialista?",
              text: "Se guardará la información en el sistema.",
              confirmButtonText: "Sí, agregar",
              cancelButtonText: "Cancelar",
              onConfirm: () => createSpecialist(formData),
            });
            if (ok) {
              await alert.success("Creado", "Especialista agregado exitosamente.");
              router.push("/4dnn1n/content/specialists");
            }
          } catch (err) {
            await alert.error("Error", getApiErrorMessage(err));
          }
        }}
      />
    </ShowcaseSection>
  );
}
```

- [ ] **Step 3: Crear página de edición de especialista**

`src/app/4dnn1n/content/specialists/[id]/edit/page.tsx`:

```tsx
"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useParams } from "next/navigation";
import { ArrowLeft } from "lucide-react";
import { usePageTitle } from "@/hooks/usePageTitle";
import { useAuth } from "@/context/AuthContext";
import { ShowcaseSection } from "@/components/Layouts/showcase-section";
import { Button } from "@/components/ui-elements/button";
import { alert } from "@/lib/alert";
import { getApiErrorMessage } from "@/lib/getApiErrorMessage";
import SpecialistForm from "../../_components/SpecialistForm";
import { getSpecialists, updateSpecialist, type ApiSpecialist } from "../../fetch";

export default function EditSpecialistPage() {
  usePageTitle("Editar Especialista");
  const router = useRouter();
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const [specialist, setSpecialist] = useState<ApiSpecialist | null>(null);

  useEffect(() => {
    if (user && user.type !== 1) router.replace("/4dnn1n/content");
  }, [user, router]);

  useEffect(() => {
    getSpecialists().then((list) => {
      const found = list.find((s) => String(s.id) === String(id));
      if (!found) router.replace("/4dnn1n/content/specialists");
      else setSpecialist(found);
    });
  }, [id, router]);

  if (!user || user.type !== 1 || !specialist) return null;

  return (
    <ShowcaseSection
      title="Editar Especialista de la Salud"
      description="Modifica la información del médico"
      actions={
        <Link href="/4dnn1n/content/specialists">
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
      <SpecialistForm
        mode="edit"
        initial={specialist}
        onSubmit={async (formData) => {
          try {
            const ok = await alert.confirm({
              title: "¿Actualizar especialista?",
              text: "Se guardarán los cambios.",
              confirmButtonText: "Sí, actualizar",
              cancelButtonText: "Cancelar",
              onConfirm: () => updateSpecialist(specialist.id, formData),
            });
            if (ok) {
              await alert.success("Actualizado", "Especialista actualizado correctamente.");
              router.push("/4dnn1n/content/specialists");
            }
          } catch (err) {
            await alert.error("Error", getApiErrorMessage(err));
          }
        }}
      />
    </ShowcaseSection>
  );
}
```

- [ ] **Step 4: Commit frontend completo**

```bash
git add \
  src/components/Layouts/sidebar/data/index.ts \
  src/app/4dnn1n/content/ 
git commit -m "feat: módulo administración de contenido — aliados y especialistas"
```

---

## Notas importantes de implementación

### Laravel method spoofing para uploads PUT
Las rutas PUT de `apiResource` no aceptan `multipart/form-data` directamente. El patrón usado es: enviar `POST` al endpoint con `_method=PUT` en el FormData. Laravel lo detecta via `MethodNotAllowedHttpException` handler y lo procesa como PUT. Esto está habilitado por defecto en Laravel.

### SSL local en tests y desarrollo
El `ContentAllyController` y `ContentSpecialistController` no realizan llamadas HTTP externas, por lo que no necesitan `Http::withoutVerifying()`.

### `storage:link` en producción
Las imágenes se sirven en `{APP_URL}/storage/content_allies/...`. Requiere `php artisan storage:link` una vez configurado el servidor (ya documentado en CLAUDE.md para los carnets).

### Orden de rutas
Las rutas `content-allies/reorder` y `content-specialists/reorder` están registradas **antes** del `apiResource` correspondiente, siguiendo la regla crítica de CLAUDE.md.
