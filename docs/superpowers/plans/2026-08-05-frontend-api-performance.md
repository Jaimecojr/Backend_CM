# Rendimiento Frontend → API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar el costo de red innecesario que hace que login y los módulos del panel (afiliados, citas) se sientan lentos en el navegador, aunque la API responda rápido quando se prueba directamente (backend confirmado rápido vía Postman: 435ms).

**Architecture:** Los cambios son independientes entre sí y no tocan lógica de negocio del backend. Se agrupan en: (1) un ajuste de configuración CORS en Laravel para que el navegador cachee el preflight `OPTIONS`, (2) eliminar un round-trip redundante servidor→Laravel en el middleware de Next.js, (3-4) hacer que las funciones de fetch del frontend no disparen preflight en peticiones `GET` y no repitan la obtención del token CSRF en cada mutación, y (5) limpieza de código muerto de la plantilla original que no afecta el rendimiento medido pero puede confundir a futuro.

**Tech Stack:** Laravel 11 (PHPUnit), Next.js App Router (TypeScript, sin test runner configurado — verificación manual vía DevTools donde no aplica un test automatizado).

## Global Constraints

- Comentarios, mensajes y nombres de variables descriptivas en **español**, según CLAUDE.md del proyecto backend.
- No hacer commits ni push — el usuario verificará manualmente antes de integrar nada. Cada tarea de este plan **NO debe terminar en `git commit`**; en su lugar, termina en un checkpoint de verificación manual.
- El frontend (`frontend-cm`) no tiene jest/vitest/playwright configurado. Las tareas de frontend usan verificación manual (curl/PowerShell para headers HTTP, DevTools del navegador para comportamiento) en lugar de tests automatizados — documentado explícitamente en cada tarea.
- No modificar `Tables/fetch.ts` ni sus `setTimeout` — está conectado a una página real y alcanzable (`/4dnn1n/tables`, enlazada desde el sidebar), a diferencia de lo que se asumió inicialmente en el spec. Su posible eliminación es una decisión de producto (¿se usa esa sección?), no de rendimiento, y queda fuera de este plan.

---

### Task 1: CORS — cachear el preflight OPTIONS

**Files:**
- Modify: `config/cors.php:25`
- Test: `tests/Feature/CorsPreflightTest.php` (crear)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: ninguna interfaz nueva, solo un valor de configuración (`config('cors.max_age')`).

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/CorsPreflightTest.php`:
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsPreflightTest extends TestCase
{
    public function test_preflight_cors_se_cachea_por_24_horas(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'X-XSRF-TOKEN',
        ])->options('/api/affiliates');

        $response->assertHeader('Access-Control-Max-Age', '86400');
    }
}
```

- [ ] **Step 2: Correr el test y confirmar que falla**

Run: `php artisan test --filter=test_preflight_cors_se_cachea_por_24_horas`
Expected: FAIL — el header `Access-Control-Max-Age` no existe o vale `0`.

- [ ] **Step 3: Aplicar el cambio de configuración**

En `config/cors.php`, línea 25, cambiar:
```php
'max_age' => 0,
```
por:
```php
'max_age' => 86400,
```

- [ ] **Step 4: Correr el test y confirmar que pasa**

Run: `php artisan test --filter=test_preflight_cors_se_cachea_por_24_horas`
Expected: PASS

- [ ] **Step 5: Correr toda la suite para descartar regresiones**

Run: `php artisan test`
Expected: todos los tests existentes siguen en verde (este cambio no afecta ninguna respuesta de negocio, solo el header de cache del preflight).

**No hacer commit** — dejar el cambio sin confirmar en git para que el usuario lo revise junto con el resto.

---

### Task 2: Middleware de Next.js — quitar el round-trip redundante a `/user`

**Files:**
- Modify: `frontend-cm/src/middleware.ts` (archivo completo, 34 líneas actuales)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: el gate de autenticación por cookie que las páginas bajo `/4dnn1n` siguen usando indirectamente (el guard real sigue siendo `useRequireAuth()` en `frontend-cm/src/hooks/useRequireAuth.ts`, ya existente — no se toca).

**Contexto verificado antes de escribir esta tarea:** el guard real de autenticación en el cliente es `useRequireAuth()` (`frontend-cm/src/hooks/useRequireAuth.ts:11-15`), que redirige a `/auth/sign-in` cuando `AuthContext` resuelve `user === null` tras llamar a `/user`. El middleware actual duplica esa verificación completa contra el backend en el servidor, en cada navegación. Reemplazarlo por una verificación de presencia de cookie (sin red) mantiene un gate rápido para el caso obvio (sin cookie → redirect inmediato) y deja la verificación real de sesión al único lugar donde ya se hacía correctamente.

- [ ] **Step 1: Verificar el comportamiento actual (referencia "antes")**

Con el frontend y backend corriendo, y **sin** haber iniciado sesión (sin cookies), en PowerShell:
```powershell
Invoke-WebRequest -Uri "http://localhost:3000/4dnn1n/home" -MaximumRedirection 0 -ErrorAction SilentlyContinue -UseBasicParsing | Select-Object StatusCode
```
Expected (antes y después del cambio, no debe variar): redirección (30x) hacia `/auth/sign-in`. Esto confirma que el comportamiento observable para un usuario sin sesión no cambia con este fix — lo único que cambia es que ya no se dispara una petición HTTP a Laravel para decidirlo.

- [ ] **Step 2: Reemplazar el contenido de `middleware.ts`**

Reemplazar el archivo completo por:
```ts
import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

export function middleware(req: NextRequest) {
  const url = req.nextUrl.clone();

  if (url.pathname.startsWith("/4dnn1n")) {
    // Verificación rápida de presencia de cookie, sin llamar al backend.
    // La verificación real de sesión la hace useRequireAuth() en el cliente,
    // que sí consulta /user contra Laravel.
    const tieneCookieSesion = req.cookies.has("XSRF-TOKEN");

    if (!tieneCookieSesion) {
      url.pathname = "/auth/sign-in";
      return NextResponse.redirect(url);
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/4dnn1n/:path*"],
};
```

- [ ] **Step 3: Verificar caso sin cookie (equivalente al Step 1, ya sin round-trip)**

Repetir el mismo comando de PowerShell del Step 1. Expected: mismo resultado (redirect a `/auth/sign-in`), confirmando que no se rompió el caso de usuario no autenticado.

- [ ] **Step 4: Verificar caso con sesión válida en el navegador real**

1. Iniciar sesión normalmente en `http://localhost:3000`.
2. Abrir DevTools → pestaña Network, filtrar por `Fetch/XHR`.
3. Navegar entre 2-3 páginas del panel (ej. `/4dnn1n/home` → `/4dnn1n/affiliates` → `/4dnn1n/appointments`).
4. Expected: **una sola** petición a `/user` por sesión (o ninguna adicional al navegar, ya que `AuthContext` solo la llama una vez al montar), y ninguna petición a `/user` disparada por el middleware en cada navegación (antes del fix, aparecía en el log de `php artisan serve` una petición a `/user` en cada navegación; después del fix, no debe aparecer).

**No hacer commit.**

---

### Task 3: `src/lib/api.ts` — evitar preflight en GET, cachear el token CSRF, reintentar en 419

**Files:**
- Modify: `frontend-cm/src/lib/api.ts` (archivo completo, 55 líneas actuales)

**Interfaces:**
- No consume nada de otras tareas.
- Produce: `csrf()`, `apiFetch()`, `getXsrfToken()`, `ApiError` — mismas firmas públicas que ya usan ~15 módulos (`appointments/fetch.ts`, `affiliates/fetch.ts`, `doctors/fetch.ts`, `counselors/fetch.ts`, `franchises/fetch.ts`, `agreements/fetch.ts`, etc., todos con `import { apiFetch, csrf } from "@/lib/api"`). Esta tarea NO cambia esas firmas, así que ningún módulo consumidor necesita tocarse.

**Contexto verificado antes de escribir esta tarea:** todos esos módulos llaman `await csrf()` antes de cada creación/edición/borrado individual. Como la mayoría importan `csrf` desde este archivo, hacer `csrf()` idempotente aquí (una sola petición de red real por sesión, las llamadas siguientes reutilizan la misma promesa) elimina el round-trip redundante en los ~15 archivos **sin modificarlos**.

- [ ] **Step 1: Verificar el comportamiento actual (referencia "antes")**

Con sesión iniciada en el navegador, abrir DevTools → Network, y hacer dos ediciones seguidas de cualquier registro (ej. editar un afiliado dos veces seguidas). Expected (comportamiento actual, antes del fix): aparecen **dos** peticiones a `/sanctum/csrf-cookie`, una por cada edición.

- [ ] **Step 2: Reemplazar el contenido de `src/lib/api.ts`**

Reemplazar el archivo completo por:
```ts
const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

export type ApiErrorData = {
  message?: string;
  errors?: Record<string, string[] | string>;
};

export class ApiError extends Error {
  status: number;
  data?: ApiErrorData;

  constructor(message: string, status: number, data?: ApiErrorData) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.data = data;
  }
}

export function getXsrfToken() {
  if (typeof document === "undefined") return null;
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : null;
}

// Cachea la promesa de la petición CSRF: mientras la sesión siga activa,
// la cookie XSRF-TOKEN sigue siendo válida, así que no hace falta pedirla
// de nuevo antes de cada mutación individual.
let csrfPromise: Promise<void> | null = null;

export function csrf(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = fetch(`${API_URL}/sanctum/csrf-cookie`, {
      method: "GET",
      credentials: "include",
    })
      .then(() => undefined)
      .catch((err) => {
        csrfPromise = null;
        throw err;
      });
  }
  return csrfPromise;
}

// Invalida la cookie CSRF cacheada — se usa cuando el backend responde 419
// (token CSRF vencido o inválido), para forzar pedirla de nuevo una vez.
function resetCsrf() {
  csrfPromise = null;
}

export async function apiFetch<T = any>(path: string, options: RequestInit = {}): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  const llevaBody = method !== "GET" && method !== "HEAD";

  const doFetch = () =>
    fetch(`${API_URL}${path}`, {
      ...options,
      credentials: "include",
      headers: {
        Accept: "application/json",
        // Solo se envía Content-Type en peticiones con body — en un GET
        // este header no es necesario y provoca un preflight CORS extra.
        ...(llevaBody ? { "Content-Type": "application/json" } : {}),
        "X-XSRF-TOKEN": getXsrfToken() ?? "",
        ...(options.headers || {}),
      },
    });

  let res = await doFetch();

  if (res.status === 419) {
    resetCsrf();
    await csrf();
    res = await doFetch();
  }

  const data = (await res.json().catch(() => ({}))) as any;

  if (!res.ok) {
    const message = data?.message || `Error ${res.status} al consumir API`;
    throw new ApiError(message, res.status, data);
  }

  return data as T;
}
```

- [ ] **Step 3: Verificar que el caso normal (dos ediciones seguidas) ya no repite el CSRF**

Repetir el Step 1 (dos ediciones seguidas de cualquier registro). Expected: aparece **una sola** petición a `/sanctum/csrf-cookie` en toda la sesión del navegador (o ninguna en la segunda edición en adelante), no una por cada edición.

- [ ] **Step 4: Verificar que los GET ya no llevan `Content-Type`**

En DevTools → Network, abrir cualquier petición `GET` reciente (ej. `GET /api/affiliates`) → pestaña Headers → Request Headers. Expected: no aparece `Content-Type: application/json`.

- [ ] **Step 5: Verificar el caso de recuperación en 419 (prueba manual dirigida)**

1. Con sesión iniciada, en DevTools → Application → Cookies, borrar manualmente la cookie `XSRF-TOKEN` (dejando las demás cookies intactas) para simular un token vencido.
2. Intentar crear o editar cualquier registro desde la UI.
3. Expected: la petición se recupera sola (Laravel responde 419 la primera vez, el código pide un nuevo CSRF y reintenta, la acción se completa) sin que el usuario vea un error ni tenga que recargar la página.

**No hacer commit.**

---

### Task 4: `src/app/4dnn1n/home/fetch.ts` — mismo tratamiento + eliminar funciones muertas

**Files:**
- Modify: `frontend-cm/src/app/4dnn1n/home/fetch.ts:1-153` (se elimina el bloque de `getOverviewData`/`getChatsData`, líneas 3-93 del archivo actual, y se ajustan `csrf`/`apiFetch`)

**Interfaces:**
- No consume nada de otras tareas (implementación duplicada e independiente de `src/lib/api.ts`).
- Produce: `csrf`, `apiFetch`, `getAuthUser`, `logout`, `getXsrfToken`, `getTodayAppointments`, `getDashboardStats`, `getDashboardCharts` — mismas firmas que ya consumen `AuthContext.tsx`, `charts-section.tsx`, `stats-cards.tsx`, `today-appointments-card.tsx`. Esta tarea NO cambia esas firmas.

**Contexto verificado antes de escribir esta tarea:** `getOverviewData` y `getChatsData` (líneas 3-93 del archivo actual) no están importadas en ningún otro archivo salvo `_components/chats-card.tsx` y `_components/overview-cards/index.tsx`, y esos dos componentes a su vez no están importados en `home/page.tsx` ni en ningún otro archivo del proyecto — código muerto confirmado, se elimina junto con esos dos componentes en la Task 5.

- [ ] **Step 1: Verificar el comportamiento actual (referencia "antes")**

Igual que en Task 3 Step 1, pero para las acciones de logout/carga del dashboard: en DevTools → Network, recargar `/4dnn1n/home` y confirmar que las peticiones `GET` (ej. `/api/dashboard/stats`) llevan `Content-Type: application/json` en sus Request Headers (comportamiento actual antes del fix).

- [ ] **Step 2: Eliminar `getOverviewData` y `getChatsData`**

Borrar las líneas 3 a 93 del archivo (las funciones `getOverviewData` y `getChatsData` completas, incluyendo sus comentarios `// Fake delay`). El archivo debe quedar empezando así después del import:
```ts
import { memCache, TTL_LIST, TTL_CATALOG } from "@/lib/memCache";

const API_URL = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";
```

- [ ] **Step 3: Reemplazar `csrf` y `apiFetch` con la misma lógica de la Task 3**

Reemplazar el bloque de `csrf()` y `apiFetch()` (después de la eliminación del Step 2, corresponden a lo que hoy son las líneas 97-125) por:
```ts
// Cachea la promesa de la petición CSRF: mientras la sesión siga activa,
// la cookie XSRF-TOKEN sigue siendo válida, así que no hace falta pedirla
// de nuevo en cada llamada.
let csrfPromise: Promise<void> | null = null;

export function csrf(): Promise<void> {
  if (!csrfPromise) {
    csrfPromise = fetch(`${API_URL}/sanctum/csrf-cookie`, {
      method: "GET",
      credentials: "include",
    })
      .then(() => undefined)
      .catch((err) => {
        csrfPromise = null;
        throw err;
      });
  }
  return csrfPromise;
}

function resetCsrf() {
  csrfPromise = null;
}

//
// 🔥 Fetch para rutas protegidas (que YA NO tienen /api)
//
export async function apiFetch(path: string, options: RequestInit = {}) {
  const method = (options.method ?? "GET").toUpperCase();
  const llevaBody = method !== "GET" && method !== "HEAD";

  const doFetch = () =>
    fetch(`${API_URL}${path}`, {
      ...options,
      credentials: "include",
      headers: {
        ...(llevaBody ? { "Content-Type": "application/json" } : {}),
        "X-XSRF-TOKEN": getXsrfToken() ?? "",
        ...(options.headers || {}),
      },
    });

  let res = await doFetch();

  if (res.status === 419) {
    resetCsrf();
    await csrf();
    res = await doFetch();
  }

  const data = await res.json().catch(() => ({}));

  if (!res.ok) throw { status: res.status, data };
  return data;
}
```

- [ ] **Step 4: Verificar que el archivo sigue compilando**

Run: `cd frontend-cm && npx tsc --noEmit`
Expected: sin errores nuevos relacionados a `home/fetch.ts` (puede haber errores preexistentes en otras partes del proyecto ajenos a este cambio; si los hay, confirmar que ya existían antes corriendo `git stash` + el mismo comando, y luego `git stash pop`).

- [ ] **Step 5: Verificar en el navegador**

Repetir el Step 1: recargar `/4dnn1n/home` y confirmar en DevTools que las peticiones `GET` a `/api/dashboard/stats`, `/api/dashboard/charts`, `/api/appointments/today` ya no llevan `Content-Type` en Request Headers.

**No hacer commit.**

---

### Task 5: Eliminar componentes muertos de la plantilla original

**Files:**
- Delete: `frontend-cm/src/app/4dnn1n/home/_components/chats-card.tsx`
- Delete: `frontend-cm/src/app/4dnn1n/home/_components/overview-cards/index.tsx`
- Delete: `frontend-cm/src/app/4dnn1n/home/_components/overview-cards/skeleton.tsx`
- Delete: `frontend-cm/src/components/Charts/campaign-visitors/index.tsx` (y su carpeta si queda vacía)
- Delete: `frontend-cm/src/components/Charts/payments-overview/index.tsx` (y su carpeta si queda vacía)
- Delete: `frontend-cm/src/components/Charts/used-devices/index.tsx` (y su carpeta si queda vacía)
- Delete: `frontend-cm/src/components/Charts/weeks-profit/index.tsx` (y su carpeta si queda vacía)
- Delete: `frontend-cm/src/services/charts.services.ts`

**Interfaces:**
- Consume: la eliminación de `getOverviewData`/`getChatsData` de la Task 4 (estos componentes son sus únicos consumidores).
- Produce: nada — es limpieza pura, no expone ninguna interfaz nueva.

**Contexto verificado antes de escribir esta tarea (ya confirmado por grep, no repetir la búsqueda):**
- `chats-card.tsx` y `overview-cards/index.tsx` no aparecen importados en `home/page.tsx` ni en ningún otro archivo del proyecto.
- `overview-cards/skeleton.tsx` solo lo usa `overview-cards/index.tsx`.
- `charts.services.ts` solo lo usan los 4 componentes de `components/Charts/*` listados arriba, y ninguno de esos 4 aparece importado en `charts-section.tsx` (el componente real que sí se usa en `home/page.tsx`) ni en ningún otro archivo.
- **`Tables/fetch.ts` NO se toca** — sí está conectado a una página real (`/4dnn1n/tables`, enlazada desde el sidebar en `components/Layouts/sidebar/data/index.ts:120`), a diferencia de los archivos de esta lista.

- [ ] **Step 1: Confirmar que no hay importadores nuevos antes de borrar (red de seguridad)**

Run cada uno de estos comandos y confirmar que la única coincidencia es el propio archivo a borrar (o ninguna coincidencia):
```bash
grep -rn "chats-card" frontend-cm/src --include="*.tsx" --include="*.ts"
grep -rn "overview-cards" frontend-cm/src --include="*.tsx" --include="*.ts"
grep -rln "charts.services" frontend-cm/src --include="*.tsx" --include="*.ts"
grep -rn "Charts/campaign-visitors\|Charts/payments-overview\|Charts/used-devices\|Charts/weeks-profit" frontend-cm/src --include="*.tsx" --include="*.ts"
```
Expected: cero resultados fuera de los propios archivos listados para borrar.

- [ ] **Step 2: Borrar los archivos**

```bash
rm frontend-cm/src/app/4dnn1n/home/_components/chats-card.tsx
rm -r frontend-cm/src/app/4dnn1n/home/_components/overview-cards
rm -r frontend-cm/src/components/Charts/campaign-visitors
rm -r frontend-cm/src/components/Charts/payments-overview
rm -r frontend-cm/src/components/Charts/used-devices
rm -r frontend-cm/src/components/Charts/weeks-profit
rm frontend-cm/src/services/charts.services.ts
```

- [ ] **Step 3: Verificar que el proyecto sigue compilando**

Run: `cd frontend-cm && npx tsc --noEmit`
Expected: sin errores nuevos (mismo criterio de comparación que en Task 4 Step 4).

- [ ] **Step 4: Verificar visualmente**

Correr `npm run dev` en `frontend-cm`, iniciar sesión y visitar `/4dnn1n/home`. Expected: la página carga igual que antes (estos componentes nunca se renderizaban ahí).

**No hacer commit.**

---

### Task 6: Verificación final integrada + descarte de la variable "modo dev"

**Files:** ninguno (solo verificación manual, no produce cambios de código).

**Interfaces:** consume el resultado de las Tasks 1-5 combinadas.

- [ ] **Step 1: Descartar la variable "compilación de Next en modo dev"**

```bash
cd frontend-cm
npm run build
npm run start
```
Navegar por login y por afiliados/citas en `http://localhost:3000` (con el backend corriendo en `localhost:8000`) y notar si la sensación de lentitud original ya no aparece incluso antes de comparar con `npm run dev`. Si sigue lento en modo producción, el resto de esta tarea (Steps 2-4) sigue siendo la referencia definitiva; si en dev seguía lento pero en build/start no, parte de la lentitud original era compilación on-demand de Next, no red.

- [ ] **Step 2: Confirmar caching del preflight en el navegador real**

Con `npm run dev` (o `build && start`) y el backend corriendo, iniciar sesión, abrir DevTools → Network, filtrar por `Fetch/XHR`, y navegar por 3-4 acciones distintas (ver listado de afiliados, ver listado de citas, abrir un modal de edición). Expected: ya no aparece un `OPTIONS` por cada peticion — solo en la primera de cada combinación método+ruta dentro de la ventana de 24h configurada en Task 1.

- [ ] **Step 3: Confirmar que `/user` se pide una sola vez por sesión**

En el mismo panel de Network, filtrar por `user`. Expected: una sola petición a `/user`, no una por cada navegación entre páginas del panel.

- [ ] **Step 4: Confirmar que `/sanctum/csrf-cookie` no se repite por cada mutación**

Crear, editar y eliminar 2-3 registros distintos (ej. un afiliado y una cita) en la misma sesión del navegador. Filtrar Network por `csrf-cookie`. Expected: una sola petición en toda la sesión (o ninguna nueva después de la primera), no una por cada acción.

**No hacer commit — este es el checkpoint final para que el usuario revise todo el conjunto de cambios antes de decidir si los integra.**
