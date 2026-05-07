# Contacto Médico - Backend API (Laravel)

Este archivo contiene el contexto y convenciones clave del proyecto Backend para agentes de IA. Por favor, lee esto antes de realizar cambios estructurales o añadir nuevas funcionalidades.

## Stack Técnico
- **Framework:** Laravel (PHP)
- **Base de Datos:** MySQL
- **Autenticación:** Basada en tokens (para consumo del frontend)

## Convenciones de Estado de Registros
Es crítico mantener la coherencia con los nombres y valores de los estados en la base de datos:
- **Afiliados (`affiliates`):** Usa el campo `stade`. `1` = Activo, `2` = Inactivo.
- **Asesores, Franquicias, Médicos (`doctors`):** Usa el campo `state`. `1` = Activo, `2` = Inactivo.
- **Convenios (`agreements`), Especialidades (`specialties`):** Usa el campo `state`. `1` = Activo, `0` = Inactivo.

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
- **Índices DB:** Las tablas grandes requieren índices en la base de datos para los foreign keys (FK) y para los campos usados en filtros, como un índice compuesto `(stade, id)` para optimizar búsquedas.

### 2. Módulos Pequeños (Sin Paginación)
Para catálogos simples o con pocos registros (asesores, convenios, franquicias), devolver todos los registros en una sola consulta.
- Estructura estándar JSON:
  - `message`: Mensaje de éxito.
  - `data`: Array de items en crudo (`$items`).

## Lógica de Negocio Específica
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

### Configuración en producción (Railway)
Para que el scheduler funcione en Railway se debe agregar un **Cron Job** en el servicio:
- **Comando:** `php artisan schedule:run`
- **Intervalo:** `* * * * *` (cada minuto — Laravel decide internamente qué tareas correr en ese momento)
- En Railway: Settings del servicio → Cron Jobs → agregar el comando con ese schedule.
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
GET /api/public/doctors                        → DoctorController@publicIndex
GET /api/public/specialties                    → SpecialtyController@publicIndex
GET /api/public/departments                    → DepartmentController@index
GET /api/public/departments/{department}/cities → CityController@getByDepartment
```

### Por qué no reutilizar los endpoints privados
Mover rutas privadas fuera del grupo `auth:sanctum` expone todos sus campos (incluyendo datos internos sensibles) a cualquier visitante. El patrón `publicIndex` es más seguro porque controla explícitamente qué se devuelve, independientemente de cambios futuros al método privado.

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
7. Envía via WhatsApp Cloud API usando `Http::withToken($bearer)->post(...)` con template (`header: document`, `body: text`)
8. Registra en `whatsapp_messages` siempre (éxito o fallo)
9. Si `messages[0].id` en respuesta → `affiliate->carnet = 'si'`, retorna 200

### Consideraciones importantes
- **Timestamp en filename:** Garantiza URL única en cada envío para evitar que Meta sirva versión cacheada del PDF anterior.
- **`carnet = 'si'`** solo se actualiza si Meta confirma con `messages[0].id`. Nunca se resetea a `'no'` automáticamente.
- **SSL local:** El controlador usa `Http::withoutVerifying()` en entorno `local` para evitar error de certificado SSL de cURL en Windows. En producción verifica SSL normalmente.
- **URL pública del PDF:** Requiere `php artisan storage:link` activo. En Railway agregar al startup script.
- **`wa_bearer_token`:** Columna tipo `TEXT` en la BD (los tokens de Meta son demasiado largos para `varchar(255)`).
- **Encoding:** `enc()` convierte UTF-8 → windows-1252 con `iconv` para que TCPDF renderice tildes correctamente con fuentes estándar (Helvetica).

### Pruebas locales
El PDF se genera en `storage/app/public/carnets/` — se puede abrir directamente para verificar coordenadas visualmente. Para probar el envío real a WhatsApp desde local, la URL del PDF debe ser pública (usar ngrok, no localtunnel — localtunnel muestra pantalla de bypass que Meta no puede pasar).

## Reglas Generales
1. **Idioma:** Los comentarios del código, nombres de variables descriptivas, strings de respuesta JSON y mensajes de validación deben estar en **español**.
2. **Validación:** Validar siempre el input del Request antes de procesarlo o insertarlo en la base de datos.
3. **Manejo de Errores:** Retornar códigos HTTP adecuados (200 OK, 422 Unprocessable Entity, 500 Server Error) con un formato JSON consistente.
