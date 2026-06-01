# Contacto Médico - Backend API (Laravel)

Este archivo contiene el contexto y convenciones clave del proyecto Backend para agentes de IA. Por favor, lee esto antes de realizar cambios estructurales o añadir nuevas funcionalidades.

## Stack Técnico
- **Framework:** Laravel (PHP)
- **Base de Datos:** MySQL
- **Autenticación:** Basada en tokens (para consumo del frontend)

## Autenticación: Provider MD5 + Bcrypt

Las contraseñas del sistema anterior están almacenadas como **MD5**. Las nuevas (creadas o modificadas en este sistema) se guardan en **bcrypt** gracias al cast `'password' => 'hashed'` del modelo `User`.

Para soportar ambos formatos existe un `UserProvider` personalizado:
- **Archivo:** `app/Auth/Md5UserProvider.php`
- **Registrado en:** `app/Providers/AppServiceProvider.php` bajo el nombre `md5-eloquent`
- **Configurado en:** `config/auth.php` → `providers.users.driver = 'md5-eloquent'`

### Lógica de `validateCredentials()`
- Si el hash guardado empieza con `$2y$` o `$2a$` → verifica con bcrypt (`Hash::check`)
- Cualquier otro formato → verifica con `md5($password) === $stored`

**No modificar el driver en `config/auth.php` de vuelta a `eloquent`** — haría que todos los usuarios legacy (MD5) dejen de poder iniciar sesión. La migración a bcrypt ocurre de forma transparente a medida que cada usuario cambia su contraseña.

## Convenciones de Estado de Registros
Es crítico mantener la coherencia con los nombres y valores de los estados en la base de datos:
- **Afiliados (`affiliates`):** Usa el campo `stade`. `1` = Activo, `2` = Inactivo.
- **Asesores, Franquicias, Médicos (`doctors`):** Usa el campo `state`. `1` = Activo, `2` = Inactivo.
- **Convenios (`agreements`), Especialidades (`specialties`):** Usa el campo `state`. `1` = Activo, `0` = Inactivo.
- **Solicitudes de afiliación (`membership_forms`):** Usa el campo `state`. `0` = Pendiente (visible en panel admin), `1` = Convertido (ya fue procesado — se oculta). El borrado es siempre físico (`delete()`), sin soft-delete.

### Regla de diseño: campo único para afiliados
El campo `stade` es el **único** indicador de estado de un afiliado. No existe ni debe existir un campo adicional de "baja permanente". Cuando un registro debe eliminarse definitivamente (duplicado, error de carga), se hace un borrado físico (`delete()`), no un borrado lógico. Esta decisión simplifica las consultas y evita repetir el problema del sistema anterior que tenía dos campos de estado con semánticas distintas.

### Regla de acceso para cambio manual de stade
Solo el **super administrador (`user_type = 1` o equivalente)** puede cambiar el `stade` de un afiliado de forma manual desde la interfaz. El flujo normal es: el cron lo inactiva al vencer → la renovación lo reactiva automáticamente. Ningún endpoint de actualización de afiliados debe permitir que un rol distinto al super admin modifique `stade` directamente.

## Patrones de Controladores

### 1. Módulos Grandes (Paginación)
Para tablas con gran volumen de datos (ej. `affiliates`), se debe usar paginación de Laravel y soportar parámetros de filtro en el request (`stade`, `search`).
- Leer `per_page` (por defecto 20), `stade` (o `state`), y `search` de la query string.
- Retornar la respuesta con estructura estándar JSON:
  - `message`: Mensaje de éxito.
  - `data`: Array de items usando `$paginated->items()`.
  - `meta`: Objeto con `current_page`, `last_page`, `per_page`, y `total`.
- **Índices DB:** Las tablas grandes requieren índices en la base de datos para:
  - Todos los foreign keys (FK): `specialty_id`, `city_id`, `user_id`, `counselor_id`, etc.
  - Campos usados en filtros: `state`, `stade`, `validity_end`, `date`, etc.
  - Campos usados en búsquedas de texto: `id_card` (para `LIKE` exacto o búsqueda por cédula).
  - Un índice compuesto `(stade, id)` optimiza las consultas con filtro de estado + paginación.
  - Agregar los índices en una **única migración consolidada** para ese módulo o sprint — no crear una migración por índice.
- **`select()` explícito en `index()`:** siempre que el `index()` use `with()` con relaciones, limitar las columnas cargadas para evitar traer datos innecesarios:
  ```php
  // Bien: solo columnas necesarias para la tabla del listado
  $query = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'stade', 'city_id', ...])
      ->with(['city:id,name', 'counselor:id,name,lastname']);
  
  // Mal: trae photo, notas, campos internos que la tabla nunca muestra
  $query = Affiliate::with(['city', 'counselor']);
  ```
  Cuando se usa `leftJoin`, usar `select('tabla.*')` para evitar que columnas del join sobreescriban campos del modelo.
- **`orderBy()` en lugar de `sortBy()`:** nunca ordenar colecciones en PHP con `->get()->sortBy()` — genera un `SELECT *` sin orden y luego ordena en memoria. Usar siempre `->orderBy('campo')->get()` para que el motor de base de datos haga el trabajo.

### 2. Módulos Pequeños (Sin Paginación)
Para catálogos simples o con pocos registros (asesores, convenios, franquicias), devolver todos los registros en una sola consulta.
- Estructura estándar JSON:
  - `message`: Mensaje de éxito.
  - `data`: Array de items en crudo (`$items`).

### 3. Búsqueda en relaciones: `leftJoin` en lugar de `orWhereHas`
Cuando la búsqueda cruza una relación (ej. buscar por nombre del médico asociado a una cita), **nunca usar `orWhereHas`** — genera una subconsulta correlacionada por cada fila y es muy lento.

Usar siempre `leftJoin` + `select('tabla_principal.*')`:
```php
$query = Modelo::with([...])->select('appointments.*');

if ($search) {
    $query->leftJoin('doctors as srch_doc', 'srch_doc.id', '=', 'appointments.doctor_id')
          ->where(function ($q) use ($search) {
              $q->where('appointments.name', 'like', "%{$search}%")
                ->orWhere('srch_doc.name', 'like', "%{$search}%")
                ->orWhere('srch_doc.lastname', 'like', "%{$search}%");
          });
}
```
- El `leftJoin` solo se aplica cuando hay búsqueda activa para no afectar consultas normales.
- El alias en el join (`srch_doc`) evita colisión de nombres con el eager load que también usa `doctors`.
- El `select('appointments.*')` es obligatorio cuando hay joins para evitar que columnas del join sobreescriban campos del modelo principal.

## Lógica de Negocio Específica

**Citas (`appointments`):**
- `type`: `1` = titular (afiliado), `2` = beneficiario.
- `afi_code`: código del afiliado titular cuando `type = 1`; código del beneficiario cuando `type = 2`.
- `owner`: campo calculado en backend — `affiliate` si `type = 1`, `beneficiary` si `type = 2`. Se normaliza en `index()` y `show()` antes de retornar; nunca se persiste.
- **Filtro de período:** acepta param `period` (`pending` = `date >= hoy`, `past` = `date < hoy`, `all` = sin filtro). Default `pending`. Si se recibe `date` (fecha exacta), ignora `period`.
- **Ordenamiento por período:** `pending` → ascendente (las más próximas primero); `past` y `all` → descendente.
- **Qué NO cargar en `index()`:** la relación `user` no se muestra en la tabla del listado — omitirla del `with()` ahorra una query por página. Solo cargar `doctor`, `city`, `affiliate`, `beneficiary`.
- **Índice en `date`:** crítico para que los filtros de período sean eficientes.

**Afiliados (`affiliates`):**
- `validity`: Fecha inicial del afiliado. Es **inmutable**; no se actualiza al editar el registro, ya que sirve para la auditoría de antigüedad.
- `validity_end`: Fecha de vencimiento. Solo se actualiza mediante renovaciones.
- `sale_date`: Fecha de última transacción. Solo se actualiza mediante renovaciones.
- El registro de renovaciones se guarda en una tabla separada llamada `renovations`.

## Reglas de Validación de Campos Comunes
Al definir las reglas del `Validator::make()` en cualquier controlador, aplica siempre:

| Campo | Regla Laravel |
|---|---|
| `movil` (celular) | `'nullable\|digits:10'` — exactamente 10 dígitos numéricos |
| `phone` (teléfono) | `'nullable\|string\|max:255'` — libre (la restricción de formato es solo frontend) |
| `value_agreement` / `amount` (valor) | `'required\|numeric\|min:10000'` o `'nullable\|numeric\|min:10000'` según si es obligatorio |

## Lógica de Vencimiento de Afiliados (Scheduler)

El sistema automatiza el cambio de estado de afiliados vencidos mediante el scheduler de Laravel.

### Comando artisan
- **Archivo:** `app/Console/Commands/UpdateExpiredAffiliates.php`
- **Firma:** `affiliates:update-expired`
- **Lógica:** Busca afiliados con `stade = 1` y `validity_end < hoy`, y los actualiza a `stade = 2`.
- **Registro:** `routes/console.php` — corre diario a las 00:05 con `Schedule::command(...)->dailyAt('00:05')`.

### Endpoint de alerta diaria
- **Ruta:** `GET /api/affiliates/expiring-today`
- **Método:** `AffiliateController@expiringToday`
- **Lógica:** Retorna afiliados con `stade = 1` y `validity_end = hoy` (los que vencen hoy, aún activos).
- **Uso:** El dashboard del frontend muestra esta lista como alerta para que los asesores gestionen renovaciones.
- La ruta **debe estar definida antes** del `Route::apiResource('affiliates', ...)` para evitar conflictos de resolución.

### Reactivación al renovar
- Al crear una renovación (`RenovationController@store`), si el afiliado tenía `stade = 2` se reactiva automáticamente a `stade = 1`.
- Esto actúa como **respaldo de backend**: el frontend también envía `stade = 1` al renovar, pero esta lógica cubre el caso de llamadas directas al endpoint de renovaciones.
- **No** se debe reactivar el afiliado solo por editarlo — solo la renovación (o el toggle manual) debe cambiar `stade`.

### Configuración en producción
Para que el scheduler funcione en producción se debe configurar un **Cron Job** en el servidor:
- **Comando:** `php artisan schedule:run`
- **Intervalo:** `* * * * *` (cada minuto — Laravel decide internamente qué tareas correr en ese momento)
- Agregar el cron job en el panel del servidor apuntando al PHP del proyecto.
- Sin este cron configurado, el comando `affiliates:update-expired` nunca se ejecutará automáticamente.

## Rutas Públicas (Sitio Web)

Las rutas que consume el sitio web público (`/web`) **no deben estar bajo el middleware `auth:sanctum`**. Se agrupan bajo el prefijo `/api/public/` y tienen sus propios métodos en los controladores.

### Convención
- Las rutas públicas van **antes** del grupo `auth:sanctum` en `routes/api.php`.
- Cada controlador con datos públicos implementa un método `publicIndex` separado del `index` privado.
- El método `publicIndex` debe:
  1. Forzar `state = 1` (solo registros activos).
  2. Retornar **únicamente los campos seguros** — nunca exponer `value_agreement`, `secretary_name`, `email`, ni campos internos.
  3. Limitar `per_page` con un tope máximo (ej. `min($perPage, 50)`).

### Rutas actuales
```
GET  /api/public/doctors                         → DoctorController@publicIndex
GET  /api/public/specialties                     → SpecialtyController@publicIndex
GET  /api/public/departments                     → DepartmentController@index
GET  /api/public/departments/{department}/cities → CityController@getByDepartment
POST /api/public/affiliate-request              → MembershipFormController@store
GET  /api/public/content-allies                 → ContentAllyController@publicIndex
GET  /api/public/content-specialists            → ContentSpecialistController@publicIndex
```

### Por qué no reutilizar los endpoints privados
Mover rutas privadas fuera del grupo `auth:sanctum` expone todos sus campos (incluyendo datos internos sensibles) a cualquier visitante. El patrón `publicIndex` es más seguro porque controla explícitamente qué se devuelve, independientemente de cambios futuros al método privado.

## Integración WhatsApp Cloud API

### Tabla `whatsapp_messages`
Registra todos los envíos de WhatsApp del sistema. Columna `type` distingue el origen:
- `'carnet'` → envío de carnet de afiliado (`CarnetController`)
- `'cita'` → notificación de cita médica (`AppointmentController`)

Siempre se registra el resultado, tanto si fue exitoso como si falló.

### Configuración (`settings`)
Todos los parámetros WA viven en la tabla `settings` (singleton — siempre hay una sola fila):
- `wa_api_version`: versión de la Graph API (ej. `v18.0`)
- `wa_phone_number_id`: ID del número de teléfono en Meta
- `wa_bearer_token`: token de acceso — columna tipo `TEXT` (los tokens de Meta superan `varchar(255)`)
- `wa_template_name`: nombre de la plantilla para carnets
- `wa_appointment_template_name`: nombre de la plantilla para confirmación de citas (nullable)

### Código de idioma para plantillas Meta
**Crítico:** Spanish (COL) en Meta Business = código `es_CO`, **NO** `es`. Usar `es` da error `#132001 template does not exist in es`. Siempre usar `'language' => ['code' => 'es_CO']` en los payloads.

### Consideraciones comunes a todos los envíos
- **SSL local:** Usar `Http::withoutVerifying()` en entorno `local` para evitar error de certificado SSL de cURL en Windows. En producción verifica SSL normalmente.
- **`wa_bearer_token`:** Validar sin `max:255` — la regla correcta es `'required|string'` (sin límite de longitud).

## Envío de Carnets por WhatsApp

### Controlador
- **Archivo:** `app/Http/Controllers/CarnetController.php`
- **Ruta:** `POST /api/affiliates/{id}/carnet` → `CarnetController@send` (dentro de `auth:sanctum`)
- **Dependencias PHP:** `setasign/fpdi` + `tecnickcom/tcpdf` (instaladas en `vendor/`)
- **Plantilla PDF:** `resources/pdf/carnet.pdf`

### Flujo del método `send($id)`
1. Busca afiliado con `Affiliate::with('beneficiaries')->find($id)` — 404 si no existe
2. Valida `movil` con `preg_match('/^\d{10}$/')` — 422 si inválido
3. Verifica los 4 campos de WhatsApp en `Setting::first()` — 500 si incompletos
4. Valida que `validity_end` no sea null — 422 si vacío
5. Franquicias: `User::where('state', 1)->where('type', 2)->get()`
6. Genera PDF con FPDI sobre `resources/pdf/carnet.pdf`, guarda en `storage/app/public/carnets/carnet_{id}_{timestamp}.pdf`
7. Envía via WhatsApp Cloud API con template (`header: document`, `body: text`), `type = 'carnet'`
8. Registra en `whatsapp_messages` siempre (éxito o fallo)
9. Si `messages[0].id` en respuesta → `affiliate->carnet = 'si'`, retorna 200

### Consideraciones importantes
- **Timestamp en filename:** Garantiza URL única en cada envío para evitar que Meta sirva versión cacheada del PDF anterior.
- **`carnet = 'si'`** solo se actualiza si Meta confirma con `messages[0].id`. Nunca se resetea a `'no'` automáticamente.
- **URL pública del PDF:** Requiere `php artisan storage:link` activo. Ejecutar una vez al configurar el servidor de producción.
- **Encoding:** `enc()` convierte UTF-8 → windows-1252 con `iconv` para que TCPDF renderice tildes correctamente con fuentes estándar (Helvetica).

### Pruebas locales
El PDF se genera en `storage/app/public/carnets/` — se puede abrir directamente para verificar coordenadas visualmente. Para probar el envío real a WhatsApp desde local, la URL del PDF debe ser pública (usar ngrok, no localtunnel — localtunnel muestra pantalla de bypass que Meta no puede pasar).

## Notificación WhatsApp al Crear/Editar Citas

### Flujo
- **Método:** `AppointmentController::enviarNotificacionWA(Appointment $appointment): array`
- Se llama al final de `store()` y `update()`, **después** de persistir la cita.
- No es bloqueante: si falla el envío, la cita ya está guardada. El resultado WA se retorna en la clave `whatsapp` del JSON de respuesta.

### Datos usados para el envío
- **Teléfono:** `appointments.phone` (no el `movil` del afiliado). Se normaliza con `preg_replace('/\D/', '', ...)` y se valida que tenga exactamente 10 dígitos. Se prefija `'57'` para el destinatario.
- **Relaciones:** `loadMissing('doctor.specialty')` para no recargar si ya estaban cargadas.

### Variables de la plantilla (7 parámetros de body, categoría UTILITY)
1. `name` — nombre del paciente en la cita
2. fecha formateada `d/m/Y`
3. `hour` — hora de la cita
4. `address` — dirección de la cita
5. especialidad del médico (`doctor.specialty.name`)
6. nombre completo del médico (`doctor.name + doctor.lastname`)
7. valor formateado `'$ ' . number_format($value, 0, ',', '.')`

### Registro en `whatsapp_messages`
Se registra con `type = 'cita'` siempre (éxito o fallo), para distinguirlo de los carnets en reportes futuros.

### Respuesta JSON
```json
{ "message": "...", "data": {...}, "whatsapp": { "enviado": true } }
// o en caso de fallo:
{ "message": "...", "data": {...}, "whatsapp": { "enviado": false, "detalle": "..." } }
```

## Dashboard

### Rutas estáticas antes de `apiResource`
**Regla crítica:** Registrar siempre las rutas estáticas **antes** del `Route::apiResource()` correspondiente. Si se registran después, Laravel interpreta el segmento estático (ej. `"today"`, `"stats"`) como el parámetro `{appointment}` del resource y falla con 404 o model-not-found.

```php
// CORRECTO
Route::get('appointments/today', [AppointmentController::class, 'today']); // primero
Route::apiResource('appointments', AppointmentController::class);           // después

// INCORRECTO — "today" se resuelve como {appointment}
Route::apiResource('appointments', AppointmentController::class);
Route::get('appointments/today', ...); // nunca se alcanza
```

### `AppointmentController::today()`
- **Ruta:** `GET /api/appointments/today`
- **Archivo:** `app/Http/Controllers/AppointmentController.php`
- Retorna citas del día actual (fecha = hoy) ordenadas por hora, con relación `doctor:id,name,lastname`.
- Filtra por `user_id` para `type !== 1`; el super admin (`type === 1`) ve todas.
- Campos del select: `id`, `name`, `hour`, `doctor_id`.
- Respuesta: `{ message, data: [...], date: "YYYY-MM-DD" }`.

### `DashboardController`
- **Archivo:** `app/Http/Controllers/DashboardController.php`

**`GET /api/dashboard/stats`** — Solo `type === 1` (403 para otros roles).
Retorna métricas globales:
```json
{
  "data": {
    "affiliates": { "active": n, "inactive": n, "inactive_by_expiry": n },
    "appointments": { "this_month": n }
  }
}
```
- `inactive_by_expiry`: afiliados con `stade=2` y `validity_end < hoy`.

**`GET /api/dashboard/charts?year=YYYY`** — Todos los roles. Year por defecto = año actual.
Retorna arrays de 12 posiciones (índice 0 = enero):
- `appointments_by_month`: citas por mes del año solicitado.
- `affiliates_by_month`: nuevos afiliados por mes (`sale_date`).
- `by_franchise` (solo `type === 1`): arrays por franquicia activa (`type=2, state=1`).

**Compatibilidad SQLite/MySQL:** Para tests con SQLite usar `strftime('%m', ...)`. Detectar con `config('database.default') === 'sqlite'`.

## Módulo Solicitudes de Afiliación (`membership_forms`)

- **Tabla:** `membership_forms`. Campo `state`: `0` = Pendiente, `1` = Convertido (ya procesado).
- **Ruta pública:** `POST /api/public/affiliate-request` → `MembershipFormController@store`
- **Rutas admin:** index/show/destroy vía `apiResource` + `PATCH /api/membership-forms/{id}/convert`
- `store()` crea la solicitud con `state = 0`. `markConverted($id)` pasa a `state = 1` y la saca del listado.
- `index()` filtra solo `state = 0` (pendientes). Las convertidas desaparecen del panel automáticamente.
- Borrado siempre físico (`delete()`), sin soft-delete.

## Módulo Mensajes de Contacto (`contacts`)

- **Tabla:** `contacts`. Sin campo de estado — solo lectura y eliminación física.
- **Columnas:** `name`, `email`, `phone`, `city_id` (FK → `cities`), `subject`, `comment`, `timestamps`. No tiene `address`.
- **Migración:** `2025_09_12_043132_create_contacts_table.php` — rediseñada con `Schema::dropIfExists` al inicio. Si se corre `migrate:fresh`, los datos se pierden (no hay datos de producción en esta tabla).
- **Ruta pública:** `POST /api/public/contact` → `ContactController@store`
- **Rutas admin:** `Route::apiResource('contacts', ContactController::class)->only(['index', 'show', 'destroy'])`
- **Mapeo de campos del formulario público a columnas DB:**

| Campo request | Columna DB | Regla de validación |
|---|---|---|
| `movil` | `phone` | `required\|digits:10` |
| `asunto` | `subject` | `required\|string\|max:255` |
| `mensaje` | `comment` | `required\|string\|min:10` |
| `recaptcha_token` | — | ignorado en backend |

- `index()`: paginado, búsqueda por `name`/`email` (LIKE), carga `city:id,name`, orden `id desc`.
- `destroy($id)`: hard delete físico. Sin soft-delete ni campo de estado.

## Reglas Generales
1. **Idioma:** Los comentarios del código, nombres de variables descriptivas, strings de respuesta JSON y mensajes de validación deben estar en **español**.
2. **Validación:** Validar siempre el input del Request antes de procesarlo o insertarlo en la base de datos.
3. **Manejo de Errores:** Retornar códigos HTTP adecuados (200 OK, 422 Unprocessable Entity, 500 Server Error) con un formato JSON consistente.
