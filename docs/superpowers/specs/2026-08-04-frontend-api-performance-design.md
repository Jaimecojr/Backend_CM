# Rendimiento: Peticiones lentas Frontend → API (login y módulos)

**Fecha:** 2026-08-04
**Proyecto:** Contacto Médico — Backend (api-cm) + Frontend (frontend-cm)

## Resumen

El backend responde rápido quando se prueba directamente (validado por Postman: `GET /api/appointments?period=all&per_page=100` → 435ms). Sin embargo, desde el navegador (login y módulos de afiliados/citas) la sensación de lentitud es real. Postman no pasa por CORS ni por el middleware de Next.js, así que no ve estos costos — de ahí la diferencia.

La causa no es un único cuello de botella sino la combinación de 5 factores, listados de mayor a menor impacto estimado. Cada uno se puede corregir de forma independiente y de bajo riesgo.

---

## 1. CORS sin cache de preflight (mayor impacto esperado)

**Archivo:** [config/cors.php](../../../config/cors.php)

**Estado actual:**
```php
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'max_age' => 0,   // línea 25
'supports_credentials' => true,
```

Y en el frontend, `apiFetch` (`frontend-cm/src/lib/api.ts:37-43` y `frontend-cm/src/app/4dnn1n/home/fetch.ts:114-118`) agrega siempre:
```ts
headers: {
  Accept: "application/json",
  "Content-Type": "application/json",
  "X-XSRF-TOKEN": getXsrfToken() ?? "",
  ...
}
```

**Por qué es un problema:** `Content-Type: application/json` y `X-XSRF-TOKEN` son headers "no simples" según la spec de CORS. Cualquier petición que los incluya — **incluso un `GET` sin body** — deja de calificar como "simple request" y el navegador dispara un preflight `OPTIONS` antes de la petición real. Con `max_age => 0`, ese preflight **nunca se cachea**, así que se repite en cada petición, sin excepción. El resultado: cada acción del usuario cuesta 2 round-trips en vez de 1 (3 en creates/updates, ver punto 4).

**Cambio propuesto:**
- `config/cors.php`: subir `max_age` a un valor razonable (ej. `86400` = 24h). El navegador cacheará el resultado del preflight por ese tiempo y no lo repetirá en cada petición mientras no cambien método/headers.
- En el frontend, no enviar `Content-Type: application/json` en peticiones `GET` (no llevan body, no lo necesitan). Esto evita el preflight por completo en las lecturas, que son la mayoría de las peticiones del panel.

**Riesgo:** bajo. `max_age` es solo una directiva de cache del navegador para el preflight, no afecta seguridad ni el comportamiento real de CORS. Quitar `Content-Type` de los GET no cambia la respuesta del backend (Laravel no depende de ese header para leer query params).

---

## 2. Middleware de Next.js duplica la verificación de sesión

**Archivo:** [frontend-cm/src/middleware.ts](../../../../frontend-cm/src/middleware.ts)

**Estado actual:**
```ts
export async function middleware(req: NextRequest) {
  if (url.pathname.startsWith("/4dnn1n")) {
    const res = await fetch("http://localhost:8000/user", { ... }); // línea 9
    ...
  }
}
export const config = { matcher: ["/4dnn1n/:path*"] };
```

Y en paralelo, `AuthContext.tsx:24-26`:
```ts
useEffect(() => {
  refreshUser(); // llama a getAuthUser() → GET /user otra vez
}, []);
```

**Por qué es un problema:**
- El middleware corre **en el servidor de Next**, en cada navegación dentro de `/4dnn1n` (el App Router lo invoca también en los fetches de RSC al navegar entre páginas del panel, no solo en la carga inicial). Esto agrega un round-trip servidor→Laravel **bloqueante** antes de poder renderizar cualquier página.
- Al montar el cliente, `AuthContext` vuelve a pedir `/user` — la misma información, una segunda vez.
- La URL está **hardcodeada** (`http://localhost:8000`, línea 9) en vez de usar la variable de entorno que sí se usa en el resto del proyecto (`NEXT_PUBLIC_API_URL`, ver `src/lib/api.ts:1`). Si el entorno cambia (staging, producción), este fetch queda apuntando a un `localhost` que no existe desde el servidor, y cada navegación pagaría el timeout completo de conexión antes de fallar.

**Cambio propuesto:**
- Usar una variable de entorno para la URL del middleware (server-side), consistente con el resto del proyecto.
- Evaluar si el middleware realmente necesita golpear la API en cada navegación, o si basta con verificar la *presencia* de la cookie de sesión (sin round-trip) y dejar que `AuthContext` haga la única verificación real contra `/user` del lado del cliente. Esto elimina la duplicación y el round-trip bloqueante del servidor en cada click de navegación.

**Riesgo:** medio — hay que decidir el nivel de protección que se quiere mantener en el middleware (verificación de cookie presente vs. verificación real contra el backend). Se debe discutir en el plan antes de tocar código, ya que toca el flujo de autenticación.

---

## 3. Modo desarrollo sin Turbopack (falso positivo de diagnóstico)

**Archivo:** [frontend-cm/package.json](../../../../frontend-cm/package.json)
```json
"dev": "next dev"
```

**Por qué es un problema:** en `next dev` sin Turbopack, cada ruta se compila on-demand la primera vez que se visita en una sesión — puede tardar varios segundos y se siente como "la API está lenta" cuando en realidad es compilación de Next, no una petición de red.

**Cambio propuesto:** no es un fix de código — es un paso de **validación** antes de invertir más esfuerzo: correr `next build && next start` (modo producción) o `next dev --turbo`, y volver a medir la sensación de lentitud en login/módulos para descartar esta variable del diagnóstico final.

**Riesgo:** ninguno (solo diagnóstico).

---

## 4. `csrf()` se llama antes de cada mutación individual

**Archivos:** todos los `fetch.ts` de módulos de escritura — `appointments/fetch.ts` (líneas 92-100), `affiliates/fetch.ts` (100, 195, 208, 227, 271, 280, 285, 293), `doctors/fetch.ts` (65, 81, 92, 97), `counselors/fetch.ts` (90, 104, 114), `franchises/fetch.ts` (72, 84, 92), `agreements/fetch.ts` (55, 67, 77), y los de `content/specialists`, `content/allies`, `membership-forms`, `account`, `settings`.

**Estado actual (patrón repetido):**
```ts
export async function createAppointment(payload) {
  await csrf();                    // round-trip extra: GET /sanctum/csrf-cookie
  const res = await apiFetch(...); // + preflight (punto 1) + petición real
  ...
}
```

**Por qué es un problema:** la cookie `XSRF-TOKEN` sigue siendo válida durante toda la sesión (no expira por cada petición). Pedirla de nuevo antes de cada creación/edición/borrado individual es más conservador de lo necesario y agrega un round-trip completo evitable por cada acción de escritura del usuario.

**Cambio propuesto:** obtener la cookie CSRF una sola vez (ej. al montar `AuthProvider`, junto al primer `refreshUser()`), y no repetirla en cada mutación. Mantener un mecanismo de reintento (llamar `csrf()` de nuevo) únicamente si una mutación falla con `419` (token expirado/inválido), como red de seguridad.

**Riesgo:** bajo-medio. Requiere probar bien el caso de sesión larga (¿la cookie XSRF puede invalidarse antes que la sesión misma?) y el caso de reintento en 419.

---

## 5. Residuos de la plantilla original con delays artificiales

**Archivos:**
- `frontend-cm/src/app/4dnn1n/home/fetch.ts:5` — `getOverviewData()` con `setTimeout(resolve, 2000)`
- `frontend-cm/src/app/4dnn1n/home/fetch.ts:29` — `getChatsData()` con `setTimeout(resolve, 1000)`
- `frontend-cm/src/services/charts.services.ts:5,44,99,148,167` — cinco `setTimeout(resolve, 1000)`
- `frontend-cm/src/components/Tables/fetch.ts:5,45,77` — `setTimeout` de 2000/1400/1500 ms

**Estado actual:** funciones de ejemplo de la plantilla `free-nextadmin-nextjs` original, con delays simulados. Verificado que **no están importadas** en `home/page.tsx` actual, por lo que hoy no afectan al dashboard real.

**Cambio propuesto:** eliminarlas junto con los componentes que las consumen (`overview-cards/index.tsx`, `chats-card.tsx`, y las tablas/gráficos de ejemplo no usados), para que no queden como trampa para un desarrollador futuro que las reactive pensando que son funcionales, o las confunda con "la API está lenta" al explorar el código.

**Riesgo:** bajo — es limpieza de código muerto, se debe confirmar con un grep de imports antes de borrar cada archivo.

---

## Fuera de alcance

- No se encontraron: timeouts artificiales activos en el flujo real, retries automáticos, interceptores de debug, ni `useEffect` en bucle infinito causando refetch descontrolado. Estas áreas se revisaron y están limpias — no requieren cambios.
- No se toca la lógica de negocio del backend (controllers, queries, índices) — el diagnóstico confirmó que no es la causa en este caso.

## Cómo verificar que quedó resuelto

1. Con DevTools → pestaña Network del navegador, filtrar por `Fetch/XHR` y confirmar que los `GET` ya no disparan `OPTIONS` (o que el `OPTIONS` desaparece en peticiones repetidas dentro de la ventana de `max_age`).
2. Confirmar en Network que `/user` se pide **una sola vez** por navegación dentro de `/4dnn1n`, no dos.
3. Repetir la prueba de "sensación de lentitud" en login y en los módulos de afiliados/citas, primero en `next dev`, luego en `next build && next start`, y comparar.
4. Confirmar que crear/editar/eliminar un registro ya no dispara una petición a `/sanctum/csrf-cookie` en cada acción (solo al iniciar sesión, o tras un 419).
