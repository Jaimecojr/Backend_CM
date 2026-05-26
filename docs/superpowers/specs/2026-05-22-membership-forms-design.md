# Diseño: Módulo Solicitudes de Afiliación

## Contexto

El sitio web público tiene un formulario de afiliación (`/web/afiliarse`) que ya existe en el frontend. Cuando un visitante lo envía, los datos deben guardarse en la tabla `membership_forms` con `state=0` (pendiente). En el panel administrador, un nuevo módulo muestra estas solicitudes pendientes con opciones para convertirlas en afiliados o eliminarlas.

---

## Sección 1 — Base de datos y modelo

### Tabla `membership_forms` (ya existe, sin cambios de migración)

| Campo | Tipo | Notas |
|---|---|---|
| id | bigint PK | |
| name | string | |
| lastname | string | |
| id_card | string | |
| phone | string | celular del solicitante (10 dígitos) |
| email | string | |
| bithdate | date nullable | typo heredado, no se corrige |
| address | string | |
| city_id | FK → cities | |
| date | date | fecha de envío del formulario |
| seller | string | nombre del asesor (texto libre, solo referencia) |
| state | tinyint default 0 | 0 = pendiente, 1 = convertido |
| timestamps | | |

### Tabla `membership_form_beneficiaries` (ya existe, sin cambios)

| Campo | Tipo |
|---|---|
| id | bigint PK |
| membership_form_id | FK → membership_forms |
| name | string |
| timestamps | |

### Ajustes al modelo `MembershipForm`

- Agregar `state` a `$fillable`
- Quitar `add` de `$fillable` (no existe en la tabla)

---

## Sección 2 — Backend API

### Controlador: `MembershipFormController`

Seguir el mismo patrón de `DoctorController`: `Validator::make`, paginación con `meta`, `with()` con columnas explícitas, respuestas JSON consistentes en español.

### Rutas públicas (sin auth)

```
POST /api/public/affiliate-request   → MembershipFormController@store
```

**`store()`** — Recibe el formulario público, crea `membership_form` + beneficiarios con `state=0`.

Validación:
- `name`, `lastname`, `id_card`, `address`, `seller`, `city_id`: `required|string`
- `phone`: `required|digits:10`
- `email`: `required|email`
- `bithdate`: `nullable|date`
- `beneficiaries`: `nullable|array`
- `beneficiaries.*`: `string`

Respuesta 201:
```json
{ "message": "Solicitud de afiliación recibida correctamente", "data": { ...membership_form } }
```

### Rutas protegidas (auth:sanctum)

```
GET    /api/membership-forms          → MembershipFormController@index
GET    /api/membership-forms/{id}     → MembershipFormController@show
DELETE /api/membership-forms/{id}     → MembershipFormController@destroy
PATCH  /api/membership-forms/{id}/convert → MembershipFormController@markConverted
```

**`index()`** — Lista solicitudes con `state=0`. Paginado, búsqueda por nombre o cédula. Relación `city:id,name`.

Parámetros query:
- `per_page` (default 20)
- `search` (filtra por `name`, `lastname`, `id_card`)

Respuesta:
```json
{
  "message": "Solicitudes obtenidas correctamente",
  "data": [...],
  "meta": { "current_page", "last_page", "per_page", "total" }
}
```

**`show()`** — Retorna una solicitud con sus beneficiarios (`with('membershipFormBeneficiaries')`). Usado por la página de nuevo afiliado al recibir `?from={id}`.

**`destroy()`** — Borrado físico (`delete()`). 404 si no existe.

**`markConverted()`** — Cambia `state=1`. 404 si no existe. Llamado por el frontend después de crear el afiliado exitosamente.

### Posición en `routes/api.php`

- La ruta pública `POST /api/public/affiliate-request` va **antes** del grupo `auth:sanctum`, junto a las otras rutas públicas.
- Las rutas protegidas van dentro del grupo `auth:sanctum`. La ruta estática `membership-forms/{id}/convert` debe registrarse **antes** de `Route::apiResource('membership-forms', ...)`.

---

## Sección 3 — Módulo admin frontend

### Ruta: `/4dnn1n/membership-forms`

Estructura igual a los demás módulos del panel (tabla paginada + búsqueda).

### Tabla — columnas

| Columna | Campo |
|---|---|
| Nombre | `name` + `lastname` |
| Cédula | `id_card` |
| Celular | `phone` |
| Ciudad | `city.name` |
| Asesor | `seller` |
| Fecha solicitud | `date` |
| Acciones | Crear afiliado / Eliminar |

### Comportamiento

- Solo muestra registros con `state=0`; los convertidos desaparecen automáticamente al refrescar.
- **"Crear afiliado"** → navega a `/4dnn1n/affiliates/new?from={id}`.
- **"Eliminar"** → confirmación previa → `DELETE /api/membership-forms/{id}` → refresca la tabla.

---

## Sección 4 — Integración con el formulario de afiliado

### Flujo de pre-carga

En `/4dnn1n/affiliates/new`, si llega el query param `?from={id}`:

1. Al montar la página, hace `GET /api/membership-forms/{id}` (incluye beneficiarios).
2. Pre-llena los campos mapeados:

| Campo `membership_form` | Campo afiliado |
|---|---|
| `name` | `name` |
| `lastname` | `lastname` |
| `id_card` | `id_card` |
| `phone` | `movil` |
| `email` | `email` |
| `bithdate` | `birthdate` |
| `address` | `address` |
| `city_id` | `city_id` |
| `beneficiaries[].name` | `beneficiaries[].name` |

3. Campos que el admin llena manualmente: `validity_end`, `sale_date`, `counselor_id`, `agreement_id`, franquicia, saldo, comisión.
4. El campo `seller` (nombre del asesor de referencia) **no** se pre-llena en ningún campo del formulario de afiliado — es solo visible en la tabla de solicitudes.

### Flujo post-guardado

Al guardar exitosamente el afiliado (respuesta 201 de `POST /api/affiliates`):
1. Llamar `PATCH /api/membership-forms/{id}/convert` para marcar la solicitud como convertida.
2. Redirigir a `/4dnn1n/membership-forms` (no a la lista de afiliados).

---

## Sección 5 — Formulario público (`/web/afiliarse`)

El componente `afiliarse/page.tsx` ya existe y está completo. Solo necesita que el backend implemente el endpoint `POST /api/public/affiliate-request`.

### Mapeo form → API

| Campo del formulario público | Campo enviado a la API |
|---|---|
| nombre | `name` |
| apellido | `lastname` |
| documento | `id_card` |
| celular | `phone` |
| email | `email` |
| fecha de nacimiento | `bithdate` |
| dirección | `address` |
| ciudad | `city_id` |
| nombre asesor | `seller` |
| beneficiarios[] | `beneficiaries[]` |
| fecha actual | `date` (generada en el backend) |

> Nota: el campo `department_id` que envía el formulario se ignora en el backend (solo se usa para filtrar ciudades en el frontend).

---

## Resumen de archivos a crear/modificar

### Backend (`api-cm`)
- `app/Models/MembershipForm.php` — ajustar `$fillable`
- `app/Http/Controllers/MembershipFormController.php` — implementar métodos
- `routes/api.php` — registrar rutas públicas y protegidas

### Frontend (`frontend-cm`)
- `src/app/4dnn1n/membership-forms/page.tsx` — página principal del módulo
- `src/app/4dnn1n/membership-forms/_components/` — componentes de tabla/acciones
- `src/app/4dnn1n/affiliates/new/page.tsx` — leer `?from` y pre-cargar datos
- `src/app/4dnn1n/affiliates/_components/AffiliateForm.tsx` — aceptar datos iniciales pre-cargados
- Navegación del panel — agregar enlace al nuevo módulo