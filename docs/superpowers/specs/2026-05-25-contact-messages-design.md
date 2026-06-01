# Spec: Módulo Mensajes de Contacto

**Fecha:** 2026-05-25
**Rama destino:** develop

---

## Contexto

El sitio web público tiene un formulario de contacto (`/web/contactenos`) que envía mensajes al endpoint `POST /api/public/contact`. El backend tiene el modelo `Contact` y el controlador `ContactController` creados pero vacíos. La tabla `contacts` existe en la DB pero con un esquema desalineado respecto al formulario (tiene `address` que el form no envía, y le falta `subject`). No hay datos en producción, por lo que se puede rediseñar la tabla sin riesgo.

El objetivo es: implementar el backend completo y crear el módulo de administración en el frontend para que los administradores puedan ver y eliminar los mensajes recibidos.

---

## 1. Base de Datos

### Migración: rediseño de tabla `contacts`

Crear una nueva migración que haga `drop` y `recreate` de la tabla con el esquema correcto:

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK auto-increment | |
| `name` | string | Nombre completo del remitente |
| `email` | string | Correo electrónico |
| `phone` | string | Celular (10 dígitos) |
| `city_id` | unsignedBigInt FK → cities | Ciudad del remitente |
| `subject` | string | Asunto seleccionado en el formulario |
| `comment` | text | Mensaje completo |
| `created_at` / `updated_at` | timestamps | `created_at` se usa como fecha de envío |

Se elimina la columna `address` (no existe en el formulario).
Se agrega la columna `subject` (asunto).
`comment` mantiene su nombre para el mensaje.

---

## 2. Backend

### Modelo `Contact`

Actualizar `$fillable`:
```php
protected $fillable = ['name', 'email', 'phone', 'city_id', 'subject', 'comment'];
```

Relación `city()` ya existe — no cambia.

### Rutas

**Pública** (sin auth, en el grupo `/api/public/`):
```
POST /api/public/contact  → ContactController@store
```

**Admin** (dentro del grupo `auth:sanctum`):
```
GET    /api/contacts        → ContactController@index
GET    /api/contacts/{id}   → ContactController@show
DELETE /api/contacts/{id}   → ContactController@destroy
```

Se registra con `Route::apiResource('contacts', ContactController::class)->only(['index', 'show', 'destroy'])`.

### `ContactController@store`

Endpoint público. Valida:

| Campo request | Regla | Mapea a |
|---|---|---|
| `name` | `required|string|max:255` | `name` |
| `movil` | `required|digits:10` | `phone` |
| `email` | `required|email|max:255` | `email` |
| `asunto` | `required|string|max:255` | `subject` |
| `city_id` | `required|exists:cities,id` | `city_id` |
| `mensaje` | `required|string|min:10` | `comment` |

El campo `recaptcha_token` se ignora en backend (validación solo frontend).
Retorna 201 con `{ message, data }`.

### `ContactController@index`

Requiere auth. Paginado (`per_page` default 20). Carga `city:id,name`. Búsqueda por `name` o `email` con `LIKE`. Orden `id desc`. Respuesta estándar con `data` + `meta`.

### `ContactController@show`

Requiere auth. Carga `city:id,name`. Retorna todos los campos. 404 si no existe.

### `ContactController@destroy`

Requiere auth. Hard delete (`delete()`). 404 si no existe. Retorna 200 con mensaje de confirmación.

---

## 3. Frontend (Panel Admin)

### Estructura de archivos

```
src/app/4dnn1n/contacts/
  fetch.ts
  page.tsx
  _components/
    columns.tsx
  [id]/
    page.tsx
```

### `fetch.ts`

Tipos:
```ts
type ApiContact = {
  id: number;
  name: string;
  email: string;
  phone: string;
  city_id: number;
  subject: string;
  comment: string;
  created_at: string;
  city?: { id: number; name: string } | null;
};
```

Funciones:
- `getContacts(params?)` → lista paginada, con caché `memCache`
- `getContact(id)` → detalle individual
- `deleteContact(id)` → DELETE + invalida caché

### `page.tsx` — Lista

- Usa `useServerTable` igual que `membership-forms/page.tsx`
- Título: "Mensajes de Contacto"
- Sin filtro de estado, con búsqueda (por nombre o correo)
- `LoadingOverlay` en carga inicial
- Confirma antes de eliminar con `alert.confirm`

### `columns.tsx` — Columnas de la tabla

Orden de columnas:
1. **Nombre** — `name`
2. **Correo** — `email`
3. **Ciudad** — `city.name`
4. **Teléfono** — `phone`
5. **Asunto** — `subject`
6. **Mensaje** — `comment` truncado a ~80 caracteres con `...`
7. **Fecha** — `created_at` formateado `dd/mm/yyyy`
8. **Acciones** — sticky right: botón Ver (navega a `/4dnn1n/contacts/{id}`) + botón Eliminar

### `[id]/page.tsx` — Detalle

- Carga `getContact(id)` con `useEffect`
- Muestra todos los campos en tarjetas (sin formulario — solo lectura)
- Botón "Volver" a `/4dnn1n/contacts`
- Botón "Eliminar" con confirmación, redirige a lista tras eliminar
- `LoadingOverlay` mientras carga

### Navegación (sidebar)

Agregar "Mensajes de Contacto" al sidebar del panel admin, apuntando a `/4dnn1n/contacts`.

---

## 4. Consideraciones

- **Hard delete**: `delete()` físico en backend y frontend. Sin soft-delete, sin campo `state`.
- **Sin paginación de servidor en detalle**: la página `/[id]` hace fetch directo por ID.
- **Fecha de envío**: se usa `created_at` del registro. No existe columna `date` separada.
- **`subject` en inglés**: el campo en DB y API se llama `subject`; en la UI se muestra como "Asunto".
- **Búsqueda**: por `name` y `email`. No por `subject` ni `comment` (simplicidad).
- **reCAPTCHA**: validación solo en frontend. Backend no verifica el token.
