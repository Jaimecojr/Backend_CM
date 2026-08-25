# Revisión de arquitectura y buenas prácticas — Backend (api-cm)

**Fecha:** 2026-08-07
**Proyecto:** Contacto Médico — Backend (api-cm)
**Tipo:** Spec de hallazgos (insumo para un plan de implementación posterior, no es el plan)

## Resumen

Auditoría de diseño de código (SOLID, separación de responsabilidades, testabilidad) sobre el backend, aparte de la revisión de rendimiento ya resuelta en `2026-08-04-frontend-api-performance-design.md`. No se reportan aquí las decisiones ya documentadas como deliberadas en `CLAUDE.md` (provider MD5+bcrypt, borrado físico sin soft-delete, campo único `stade`/`state`, patrón `leftJoin`, `publicIndex` separado de `index`).

Un hallazgo dejó de ser solo "calidad de código" y se confirmó como **bug activo**: el código de envío de carnets usa un código de idioma que el propio proyecto documentó como inválido. Se marca por separado porque tiene prioridad de arreglo inmediato, independiente del resto del spec.

---

## 0. Bug activo: código de idioma incorrecto en envío de carnet por WhatsApp

**Archivo:** [app/Http/Controllers/CarnetController.php:65](../../../app/Http/Controllers/CarnetController.php#L65)

**Estado actual:**
```php
'language'   => ['code' => 'es'],
```

**Por qué es un problema:** `CLAUDE.md` documenta explícitamente que Meta exige `es_CO` para plantillas en español (Colombia) y que `es` produce el error `#132001 template does not exist in es`. El sitio equivalente en citas ([AppointmentController.php:247](../../../app/Http/Controllers/AppointmentController.php#L247)) sí usa `es_CO` — la lógica de envío se copió entre controladores (ver hallazgo #2) y en esa copia se perdió el detalle. **El envío de carnets por WhatsApp probablemente está fallando en este momento.**

**Cambio propuesto:** cambiar `'es'` → `'es_CO'` en la línea 65. Cambio de una línea, sin riesgo.

**Cómo verificar que quedó resuelto:** enviar un carnet de prueba desde el panel y confirmar en `whatsapp_messages` que el envío queda registrado como exitoso (no con el error `#132001`).

---

## 1. Sin capa de Form Requests ni de Servicios

**Evidencia:** no existen los directorios `app/Http/Requests/` ni `app/Services/` en el proyecto.

**Por qué es un problema:** toda la validación (`Validator::make()` inline) y toda la lógica de negocio (llamadas HTTP externas, generación de PDF, cálculo de fechas de vigencia) vive directamente en los métodos de los controladores. Un controlador como `CarnetController::send()` mezcla validación + llamada a WhatsApp + generación de PDF + persistencia en ~110 líneas — no se puede testear la validación sin ejecutar también el resto, ni reusar una regla de validación sin copiar el bloque completo.

**Cambio propuesto:** no es "crear FormRequests y Services en todo el proyecto de una vez" — eso sería sobre-ingeniería para módulos simples. Empezar por los controladores con reglas de validación más repetidas y de mayor riesgo de negocio (ver hallazgo #3).

**Riesgo de no resolverlo:** cada nuevo campo o regla de validación obliga a tocar N controladores en vez de 1, con alto riesgo de que diverjan (ver hallazgo #3, ya divergieron).

---

## 2. Lógica de envío WhatsApp duplicada en 3 controladores

**Archivos:**
- [AppointmentController.php](../../../app/Http/Controllers/AppointmentController.php) (`enviarNotificacionWA`)
- [CarnetController.php](../../../app/Http/Controllers/CarnetController.php) (`send`)
- [WhatsAppWebhookController.php](../../../app/Http/Controllers/WhatsAppWebhookController.php) (autoreply)

**Estado actual:** cada uno repite: leer `Setting::first()`, validar que los 4 campos de configuración WA estén completos, armar el payload (`messaging_product`, `recipient_type`, `template`, `language`), llamar `Http::withToken(...)` (con `withoutVerifying()` en local), y registrar el resultado en `whatsapp_messages`.

**Por qué es un problema:** es la prueba viviente del hallazgo #0 — la misma lógica copiada 3 veces ya diverge en un detalle crítico (idioma de plantilla). Cualquier cambio futuro (nueva versión de la Graph API, cambio de formato de payload, nuevo campo de configuración) obliga a tocar los 3 sitios, y no hay garantía de que se actualicen los 3 igual.

**Cambio propuesto:** extraer un servicio (ej. `App\Services\WhatsAppClient`) con un método por tipo de envío (`enviarPlantillaConDocumento`, `enviarPlantillaConParametros`, o similar), que centralice: lectura de `Setting`, validación de configuración, armado de payload, llamada HTTP y registro en `whatsapp_messages`. Los 3 controladores pasan a ser clientes de ese servicio.

**Riesgo:** medio — hay que decidir la forma de la interfaz del servicio sin sobre-generalizar antes de conocer bien los 3 casos de uso (por eso es spec, no implementación todavía).

---

## 3. Regla de validación de `movil` inconsistente con lo documentado en CLAUDE.md

**Evidencia verificada (grep sobre `app/Http/Controllers`):**

| Controlador | Regla usada | ¿Cumple CLAUDE.md (`nullable\|digits:10`)? |
|---|---|---|
| `AffiliateController.php:75,171` | `'movil' => 'nullable\|string\|max:50'` | **No** |
| `UserController.php:42,119` | `'movil' => 'nullable\|string\|max:50'` | **No** |
| `CounselorController.php:65,157` | `'movil' => 'nullable\|digits:10'` | Sí |
| `DoctorController.php:159,235` | `'movil' => 'required\|digits:10'` / `'nullable\|digits:10'` | Sí |
| `ContactController.php:15` | `'movil' => 'required\|digits:10'` | Sí |
| `MembershipFormController.php:98` | `'movil' => 'required\|digits:10'` | Sí |

**Por qué es un problema:** `AffiliateController` y `UserController` (franquicias) aceptan cualquier string de hasta 50 caracteres en `movil` — el mismo campo que `CarnetController` usa después para armar el número de WhatsApp del destinatario (`'57' . $affiliate->movil`). Un valor no numérico o de longitud distinta a 10 no falla en la validación de creación/edición, pero sí puede fallar (silenciosamente o con error de Meta) al momento de enviar el carnet, mucho después y en un contexto donde es más difícil relacionar la causa.

**Cambio propuesto:** alinear `AffiliateController` y `UserController` a `'nullable|digits:10'`, igual que el resto. Cambio pequeño y de bajo riesgo, pero requiere revisar si hay datos existentes en la tabla `affiliates`/`users` que no cumplan el formato (movil con espacios, guiones, código de país) antes de endurecer la regla, para no romper ediciones de registros legacy.

**Riesgo:** bajo-medio — depende de qué tan "sucios" estén los datos actuales de `movil` en afiliados/franquicias existentes.

---

## 4. Verificación de rol repetida inline (8 ocurrencias)

**Evidencia verificada:** `grep -rn "user()->type !== 1"` sobre `app/Http/Controllers` → 8 coincidencias (`DashboardController`, y otros módulos que restringen acciones a super admin).

**Por qué es un problema:** la regla de negocio "solo el super admin (`type === 1`) puede hacer X" vive repetida como comparación inline en 8 lugares distintos, en vez de un solo punto de verdad (`Gate::define` o una `Policy`). Si el criterio de "quién es super admin" cambiara (ej. agregar un segundo rol con los mismos permisos), habría que encontrar y actualizar las 8 ocurrencias manualmente.

**Cambio propuesto:** un `Gate::define('super-admin', fn ($user) => $user->type === 1)` en un ServiceProvider, o una `AffiliatePolicy`/`AdminPolicy` según el caso, y reemplazar las comparaciones inline por `Gate::allows('super-admin')` o `$this->authorize(...)`.

**Riesgo:** bajo — es un cambio mecánico, pero conviene hacerlo junto con tests que ya cubran cada endpoint protegido (ver hallazgo #5).

---

## 5. Sin tests Feature para los módulos de mayor complejidad y riesgo de negocio

**Evidencia:** `tests/Feature/` tiene cobertura para: WhatsApp webhook, cambio de contraseña, dashboard, consulta pública de afiliado, membership forms, contenido, contactos, CORS. **No hay ningún test** para el CRUD completo de `AffiliateController`, `AppointmentController`, `DoctorController`, `CounselorController`, ni para `CarnetController::send()`.

**Por qué es un problema:** son los módulos con más lógica de negocio (vigencias, renovaciones, comisiones, envío de WhatsApp con dinero de por medio) y ninguno tiene una red de seguridad automatizada. El hallazgo #0 (bug activo de idioma) es evidencia directa de esto: un test de integración que golpeara el endpoint real de Meta con un mock habría detectado el error de plantilla antes de llegar a producción.

**Cambio propuesto:** priorizar tests Feature para `AffiliateController` (CRUD + reglas de `stade`/renovación) y `AppointmentController` (CRUD + normalización de `owner` + notificación WA con `Http::fake()`).

**Riesgo:** ninguno — es pura adición de tests, no toca código de producción.

---

## 6. Modelos anémicos / lógica de vigencia duplicada

**Evidencia:** `app/Models/Affiliate.php` y `app/Models/Appointment.php` solo declaran `$fillable`, casts y relaciones. La lógica de "¿está vencido?", "¿cuántos días faltan?" vive repetida entre `UpdateExpiredAffiliates` (comando artisan), `AffiliateController::expiringToday()` y `DashboardController::stats()` (cada uno recalculando con `Carbon::today()` y comparaciones propias).

**Por qué es un problema:** no es grave hoy porque la lógica es simple (una comparación de fechas), pero si la regla de vigencia cambiara (ej. agregar un período de gracia de N días antes de inactivar), habría que encontrar y actualizar los 3 sitios.

**Cambio propuesto:** un scope de modelo (`Affiliate::vencidos()`, `Affiliate::porVencerHoy()`) o un método de dominio (`$affiliate->estaVencido()`), usado por los 3 consumidores.

**Riesgo:** bajo — es un refactor de extracción, sin cambiar comportamiento.

---

## 7. `login`/`logout` como closures en `routes/web.php`

**Archivo:** [routes/web.php](../../../routes/web.php)

**Por qué es un problema:** es el único módulo del proyecto sin controlador dedicado — inconsistente con la convención del resto (`AffiliateController`, `AppointmentController`, etc.). Dificulta agregar tests unitarios sobre la lógica de login/logout de forma aislada y rompe el patrón que un desarrollador nuevo esperaría al buscar "dónde está la lógica de autenticación".

**Cambio propuesto:** extraer a un `AuthController` con métodos `login()`/`logout()`. Cambio mecánico, sin riesgo funcional.

**Riesgo:** bajo.

---

## Fuera de alcance de este spec

- No se reevalúa el rendimiento (ya resuelto: N+1 del dashboard, cookie `auth_hint`).
- No se propone introducir un ORM/patrón repositorio adicional sobre Eloquent — sería sobre-ingeniería para el tamaño actual del proyecto.
- No se propone testear exhaustivamente todos los controladores pequeños (catálogos: `AgreementController`, `SpecialtyController`, etc.) — su lógica es simple y el riesgo/beneficio de testearlos es bajo comparado con Affiliate/Appointment/Carnet.

## Siguiente paso

Este documento es el spec de hallazgos. El plan de implementación (orden de ejecución, qué se hace en qué PR, cómo se prueba cada paso) se escribe en un documento separado en `docs/superpowers/plans/`, priorizando el hallazgo #0 (bug activo) primero.
