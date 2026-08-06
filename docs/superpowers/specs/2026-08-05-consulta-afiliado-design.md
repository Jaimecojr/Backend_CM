# Consulta de Estado de Afiliado (Sitio Web Público)

## Contexto

El sitio web público (`frontend-cm`) ya tiene un widget de "Consulta de Afiliado" (`AffiliateConsultWidget.tsx`) embebido en el hero de la landing page, con un servicio (`affiliateService.ts`) que apunta a un endpoint público `POST /api/public/affiliate-status` que **todavía no existe** en el backend. Esta es la última pieza pendiente para terminar la página web pública.

El objetivo: un visitante ingresa su número de cédula (la del titular) y ve en un modal el estado de su grupo familiar — titular, beneficiarios y vigencia — igual al mockup de referencia proporcionado (imagen "Registro Grupo Familiar").

## Alcance

- Backend: nuevo endpoint público de solo lectura para consultar un afiliado + beneficiarios por cédula.
- Frontend: ajustes al formulario existente (tipo de documento fijo, número de documento solo numérico) y un modal nuevo para mostrar el resultado.
- Fuera de alcance: cualquier acción de edición/renovación desde el sitio público (solo consulta de lectura).

## Backend

### Endpoint

`POST /api/public/affiliate-status`

Registrado dentro del grupo `Route::prefix('public')` en `routes/api.php` (antes del grupo `auth:sanctum`), apuntando a un método **nuevo** `AffiliateController::publicStatus()` — separado del método interno existente `byIdCard()` (usado por el flujo de creación de citas bajo `auth:sanctum`) para no arriesgar ese flujo ni sus reglas de bloqueo por inactividad.

### Request

```json
{ "document_number": "1094947820" }
```

`document_type` puede llegar en el payload pero el backend lo ignora (todos los registros son cédula). Validación: `document_number` requerido, string, solo dígitos.

### Lógica

```php
$affiliate = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'stade', 'validity_end'])
    ->with(['beneficiaries:id,affiliate_id,name'])
    ->where('id_card', $idCard)
    ->first();

if (!$affiliate) {
    return response()->json([
        'success' => false,
        'message' => 'No encontramos un grupo familiar con esa cédula.',
    ], 404);
}

return response()->json([
    'success' => true,
    'message' => 'Afiliado encontrado.',
    'data' => $affiliate, // incluye stade, validity_end y beneficiaries
], 200);
```

**A diferencia de `byIdCard()` interno:** este endpoint **no bloquea** afiliados inactivos o vencidos con un error — siempre retorna 200 con los datos si el registro existe. El frontend es responsable de mostrar el aviso de estado (activo/inactivo) dentro del mismo modal, igual que en el mockup de referencia (que muestra el grupo familiar completo junto con "Afiliación Inactiva Venció Agosto 04/2025").

### Campos expuestos

Solo: `name`, `lastname`, `id_card`, `stade`, `validity_end`, `beneficiaries[].name`.

**Nunca se exponen:** `movil`, `phone`, ni ningún otro campo interno del afiliado — siguiendo la convención de rutas públicas del proyecto (ver sección "Rutas Públicas" en `CLAUDE.md`).

### Validación de input

- `document_number`: `required|string|regex:/^[0-9]+$/|max:20` (min razonable, ej. `min:5`, no crítico).
- Si la validación falla → `422` con mensaje estándar de error.

## Frontend

### Formulario (`AffiliateConsultWidget.tsx`)

- **Tipo de documento:** `<select>` se deja visible pero **deshabilitado**, con la única opción "Cédula de Ciudadanía" preseleccionada. Puramente estético — no se envía al backend, o se envía fijo (`"CC"`) y el backend simplemente lo ignora.
- **Número de documento:** input con:
  - `inputMode="numeric"` + filtrado en `onChange` que descarta cualquier caracter no numérico (nada de puntos, espacios ni letras).
  - `maxLength={20}`.
- Al enviar (éxito o error), **siempre se abre el modal** — ya no hay mensaje inline en el formulario como hoy.

### Servicio (`affiliateService.ts`)

Actualizar la interfaz de respuesta para incluir los campos nuevos:

```ts
interface AffiliateStatusResponse {
  success: boolean;
  message: string;
  data?: {
    name: string;
    lastname: string;
    id_card: string;
    stade: number;
    validity_end: string;
    beneficiaries: { name: string }[];
  };
}
```

`checkAffiliateStatus(docNum)` sigue haciendo `POST` a `/api/public/affiliate-status`; ya no necesita `docType` como parámetro funcional (puede seguir recibiéndolo y no usarlo, o eliminarse de la firma).

### Modal de resultado (nuevo componente `AffiliateStatusModal.tsx`)

Reutiliza el patrón de `LegalModal.tsx` (portal a `document.body`, gate de `mounted` para SSR, cierre con backdrop click y `Escape`, bloqueo de scroll del body mientras está abierto, borde/acento con el color de marca `#1DBFCE`).

**Diseño visual:** aplicar la skill de diseño frontend para que el modal se vea pulido y sea responsive (mobile-first, igual que el resto del sitio público), manteniendo consistencia con `LegalModal.tsx` en vez de reinventar un sistema visual nuevo.

- **Título:** "Registro Grupo Familiar".
- **Caso encontrado:**
  - Sección "Titular": nombre completo (mayúsculas) + cédula.
  - Sección "Beneficiarios": lista con nombre de cada uno, filas alternadas (como en la referencia). Si no hay beneficiarios, la sección se omite o muestra "Sin beneficiarios registrados".
  - Aviso de estado, calculado en frontend a partir de `stade` y `validity_end`:
    - Si `stade !== 1` o `validity_end` < hoy → texto en rojo: `"Afiliación Inactiva — Venció {mes}/{año}"`.
    - Si activo y vigente → texto en verde: `"Afiliación Activa — Vigente hasta {fecha}"`.
- **Caso no encontrado (404):** mismo modal, con mensaje: "No encontramos un grupo familiar con esa cédula."
- **Caso error de red/servidor:** mismo modal, mensaje genérico: "Ocurrió un error al consultar. Intenta nuevamente."
- Botón de cierre (X) en el header + click en backdrop cierran el modal.

## Manejo de errores (resumen)

| Caso | HTTP | Dónde se muestra |
|---|---|---|
| Cédula no encontrada | 404 | Modal, mensaje "no encontrado" |
| `document_number` inválido/vacío | 422 | Modal, mensaje de validación |
| Error de red/servidor | — / 500 | Modal, mensaje genérico |
| Encontrado (activo o inactivo) | 200 | Modal, datos completos + aviso de estado |

## Testing

- Backend: test de feature para `POST /api/public/affiliate-status` cubriendo: encontrado activo, encontrado inactivo/vencido (sigue retornando 200 con datos), no encontrado (404), input inválido (422), y verificación de que la respuesta no incluye `movil`/`phone`.
- Frontend: verificación manual en navegador (levantar dev server) probando: cédula válida activa, cédula válida inactiva, cédula inexistente, e input con caracteres no numéricos (confirmar que se filtran).
