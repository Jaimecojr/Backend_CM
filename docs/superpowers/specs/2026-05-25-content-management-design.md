# Módulo: Administración de Contenido

**Fecha:** 2026-05-25  
**Proyecto:** Contacto Médico — Backend (api-cm) + Frontend (frontend-cm)

## Resumen

Módulo para gestionar desde el panel administrativo el contenido visible en la página web pública:
1. **Aliados Estratégicos** — banners con enlace, máximo 6.
2. **Especialistas de la Salud** — foto y nombre del médico para el cuadro médico del homepage, máximo 4.

Ambas secciones son independientes del módulo de médicos del sistema. Son listas curadas, gestionadas manualmente por el superadmin.

---

## Base de Datos

### Tabla `content_allies`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK auto-increment | |
| `image` | string | path relativo en storage (ej. `content_allies/filename.jpg`) |
| `image_filename` | string | nombre único del archivo guardado |
| `url` | string | enlace al sitio web del aliado |
| `position` | integer | orden de aparición en la página |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Tabla `content_specialists`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK auto-increment | |
| `name` | string | nombre completo del médico |
| `photo` | string | path relativo en storage (ej. `content_specialists/filename.jpg`) |
| `photo_filename` | string | nombre único del archivo guardado |
| `position` | integer | orden de aparición en la página |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

---

## Backend (api-cm)

### Modelos

- `App\Models\ContentAlly` → tabla `content_allies`
- `App\Models\ContentSpecialist` → tabla `content_specialists`

### Controladores

**`ContentAllyController`**
- `index()` — lista todos ordenados por `position asc`. Sin paginación (máximo 6 registros).
- `store()` — valida máximo 6 registros existentes antes de crear. Guarda imagen en `storage/app/public/content_allies/`.
- `update($id)` — actualiza campos. Si se envía nueva imagen, elimina la anterior y guarda la nueva.
- `destroy($id)` — elimina registro y archivo de imagen del storage.
- `reorder()` — recibe `[{id, position}, ...]`, actualiza todas las posiciones en una transacción.
- `publicIndex()` — endpoint público, retorna solo `id`, `image_url`, `url`, `position`. Sin auth.

**`ContentSpecialistController`**  
Misma estructura, con máximo 4 registros. Campos: `name`, `photo`. Carpeta: `storage/app/public/content_specialists/`.

### Validaciones

**Aliados (`store` y `update`):**
- `image`: `required` en store, `nullable` en update | `image|mimes:jpeg,png,webp|max:2048`
- `url`: `required|string|url|max:255`
- `position`: `required|integer|min:1`

**Especialistas (`store` y `update`):**
- `name`: `required|string|max:255`
- `photo`: `required` en store, `nullable` en update | `image|mimes:jpeg,png,webp|max:2048`
- `position`: `required|integer|min:1`

### Rutas

```php
// Públicas (sin auth, antes del grupo sanctum)
Route::get('public/content-allies', [ContentAllyController::class, 'publicIndex']);
Route::get('public/content-specialists', [ContentSpecialistController::class, 'publicIndex']);

// Admin (bajo auth:sanctum, solo superadmin type=1)
Route::put('content-allies/reorder', [ContentAllyController::class, 'reorder']); // antes del apiResource
Route::apiResource('content-allies', ContentAllyController::class)->except(['show']);

Route::put('content-specialists/reorder', [ContentSpecialistController::class, 'reorder']); // antes del apiResource
Route::apiResource('content-specialists', ContentSpecialistController::class)->except(['show']);
```

### Respuesta JSON estándar

```json
// index y publicIndex
{ "message": "Aliados obtenidos", "data": [...] }

// store / update
{ "message": "Aliado creado", "data": {...} }

// destroy
{ "message": "Aliado eliminado" }

// reorder
{ "message": "Orden actualizado" }
```

### URL pública de imágenes

Las imágenes se sirven via `Storage::url($record->image)`, que genera `{APP_URL}/storage/content_allies/filename.jpg`. Requiere `php artisan storage:link` activo en producción.

### Storage de imágenes

- Carpeta aliados: `storage/app/public/content_allies/`
- Carpeta especialistas: `storage/app/public/content_specialists/`
- Nombre del archivo: `{timestamp}_{uniqid()}.{ext}` para garantizar unicidad y evitar caché.
- Al actualizar con nueva imagen: se elimina el archivo anterior con `Storage::delete()` antes de guardar el nuevo.

---

## Frontend (frontend-cm)

### Sidebar

Nuevo ítem **"Administración de Contenido"** visible solo para `user_type = 1` (superadmin). Navega a `/content`.

### Página `/content` — Landing

Dos cards centradas:
- **Aliados Estratégicos** — ícono, título, descripción breve ("Banners de empresas aliadas en la página web"), botón "Gestionar".
- **Especialistas de la Salud** — ícono, título, descripción breve ("Médicos destacados en el cuadro médico"), botón "Gestionar".

### Página `/content/allies` — Gestión de Aliados

- Tabla con columnas: thumbnail de imagen, URL (truncada), posición, acciones (Editar, Eliminar).
- Botón "Agregar aliado" en la parte superior. Se deshabilita cuando hay 6 registros, mostrando tooltip "Límite de 6 aliados alcanzado".
- Modal crear/editar: campo de imagen con preview, campo URL, campo posición numérica.
- Confirmación antes de eliminar.

### Página `/content/specialists` — Gestión de Especialistas

- Misma estructura que aliados.
- Tabla: thumbnail de foto, nombre, posición, acciones.
- Botón "Agregar especialista" deshabilitado cuando hay 4 registros.
- Modal: campo de foto con preview, campo nombre, campo posición.

### Reordenamiento

El campo `position` se edita directamente en el modal de edición de cada registro. Al guardar, se llama al endpoint `PUT /api/content-allies/reorder` (o `content-specialists/reorder`) con el array actualizado de posiciones. No se implementa drag-and-drop para mantener consistencia con el resto del panel.

---

## Seguridad

- Los endpoints admin están bajo `auth:sanctum` — solo usuarios autenticados.
- Se recomienda verificar `user_type === 1` en el controlador para las operaciones de escritura, consistente con otros módulos sensibles del proyecto.
- Los endpoints públicos exponen únicamente `id`, `image_url`, `url`, `position` (aliados) e `id`, `name`, `photo_url`, `position` (especialistas). Nunca exponen `image_filename` ni `photo_filename`.

---

## Consideraciones de producción

- Ejecutar `php artisan storage:link` una sola vez al configurar el servidor (ya documentado en CLAUDE.md para los carnets).
- Las migraciones de `content_allies` y `content_specialists` van en archivos separados con timestamp `2026-05-25`.
- La tabla `carousels` (legacy) no se toca en este módulo — queda para eliminación en un sprint posterior.
