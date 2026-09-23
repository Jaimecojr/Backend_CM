# Spec: Módulo de Reportes (migración desde cmWeb legacy)

**Fecha:** 2026-09-22
**Rama destino:** develop

---

## Contexto

El panel admin legacy (`4dnn1n/`, PHP con `mysql_*`) tiene un módulo de Reportes con 6 reportes
operativos (ventas, cartera, resumen de usuarios, citas, clientes sin renovación, carnets no
enviados) que nunca se migró a este proyecto. La base de datos es la misma en esencia, pero los
nombres de tablas/columnas cambiaron en la migración a este backend — este spec traduce cada
reporte legacy a los nombres reales de este proyecto y corrige varios bugs conocidos del legacy
(conteos que no cuadraban con el listado, exportes que solo traían la página visible, filtros de
ID con `LIKE` en vez de `=`, SQL por concatenación).

**Decisiones tomadas con el usuario durante el diseño:**
- **Reporte 5 (Sin Renovación):** el legacy filtraba `stade=1 AND validity_end<=hoy`. Este backend
  ya tiene un cron diario (`affiliates:update-expired`) que pasa a `stade=2` cualquier afiliado con
  `validity_end < hoy`. Replicar el filtro literal dejaría el reporte casi vacío (solo mostraría
  los que vencen el mismo día, antes de que corra el cron a las 00:05). Se decidió **ignorar
  `stade`** y filtrar solo por `validity_end <= hoy`, para que el reporte sirva como lista real de
  "clientes para llamar".
- **Reporte 6 (Carnets No Enviados):** en este proyecto `whatsapp_messages.deleted` se crea siempre
  en `0` (ver `WhatsAppClient::sendTemplate()`) y ningún proceso lo actualiza — ni en envíos
  exitosos ni fallidos. No existe columna de éxito/fallo; solo el JSON crudo en `response`. Se
  decidió determinar "no enviado" **parseando `response`** (ausencia de `messages.0.id`), no usando
  `deleted`. No se agrega mecanismo de "marcar resuelto" — fuera de alcance.
- **Extras "sugeridos" del legacy:** se incluyen ambos — fila de total de saldo en Reporte 2 y
  exportación a Excel de los 6 indicadores del Reporte 3.
- **Excel en los 6 reportes:** confirmado explícitamente por el usuario — los 6 reportes (incluido
  el de indicadores) tienen botón de exportación, sin excepción.

**Fuera de alcance** (igual que en el prompt original): "Reporte Carnets" (por `use_carnet='no'`,
ya desactivado en el legacy) y "Reporte de Contratos" — ninguno de los dos se migra.

---

## 0. Glosario de mapeo legacy → proyecto nuevo

Confirmado leyendo migraciones y modelos reales (no inferido):

| Legacy | Proyecto nuevo | Notas |
|---|---|---|
| `users` (titulares) | `affiliates` | ver columnas abajo |
| `users.use_code` | `affiliates.id` | |
| `users.use_fran_code` | `affiliates.user_id` | **FK directa** a `users.id` (franquicia) — no hay tabla `franchises` separada |
| `beneficiarys` | `beneficiaries` | `user_code` → `affiliate_id` |
| `counselor` | `counselors` | `cou_fran` → `counselors.user_id` (misma FK directa a franquicia) |
| `franquicia` | **no existe como tabla** | una franquicia ES un registro de `users` con `type=2`; su PK (`users.id`) es la FK usada en `affiliates.user_id`, `counselors.user_id`, `appointments.user_id` |
| `convenio` | `agreements` | `con_valor` → `agreements.amount` (**no** `value_agreement`, que vive en `doctors`) |
| `renovation` | `renovations` | `reno_user_id`→`affiliate_id`, `reno_date_ini`→`date_ini`, `reno_date_end`→`date_end`, `reno_date_payment`→`date_payment`, `reno_value`→`value` |
| `ciudad`/`departamento` | `cities`/`departments` | sin cambios de fondo |
| `appoinment` | `appointments` | `apo_code`→`id`, `apo_fran`→`appointments.user_id` (FK directa), `apo_type`→`type`, `apo_date`→`date`, `apo_hour`→`hour` |
| `doctor` | `doctors` | `doc_stade`→`state`, `doc_city` (texto) → `city_id` (FK real) + `address` (texto libre) |
| `whatsapp_message` | `whatsapp_messages` | `recipient_id` = `'57' + local` (confirmado en `WhatsAppClient::sendTemplate()`), `deleted` siempre `0` (ver Contexto), `type` distingue `'carnet'`/`'cita'` |

Roles: `users.type` — `1` = SuperAdmin (`User::isSuperAdmin()`, ya existe), `2` = Franquicia
(nuevo: `User::isFranchise()`), `3` = reservado, sin flujo de login implementado hoy. Regla de
acceso para los 6 reportes: SuperAdmin ve todo, Franquicia ve su alcance (reportes 1-5), cualquier
otro `type` recibe 403 en los 6.

---

## 1. Backend — arquitectura

### Namespace `App\Reports\`

Una clase por reporte, responsable únicamente de construir la query base (sin paginar):

```php
namespace App\Reports;

class SalesReport
{
    public function query(array $filters, User $authUser): Builder { ... }
}
```

La misma instancia de query (antes de `paginate()`) se usa para: `count()` del total, `get()` para
el Excel, y `paginate()` para el listado — nunca se duplica la condición `WHERE` en dos sitios (bug
conocido del legacy en varios reportes).

Clases: `SalesReport`, `BalanceReport`, `AffiliatesSummaryReport`, `AppointmentsReport`,
`NonRenewedAffiliatesReport`, `UnsentCarnetsReport`.

### `App\Reports\Concerns\AppliesFranchiseScope`

Trait reutilizable, aplicado en los 5 reportes que se acotan por franquicia (no en
`UnsentCarnetsReport`, exclusivo de super admin):

```php
trait AppliesFranchiseScope
{
    protected function scopeFranchise(Builder $query, string $column, User $authUser): Builder
    {
        if (!$authUser->isSuperAdmin()) {
            $query->where($column, $authUser->id);
        }

        return $query;
    }
}
```

Cualquier `fran_code`/`franchise_id` que venga en el request se ignora por completo cuando el
usuario autenticado no es super admin — nunca se confía en ese parámetro (regla explícita del
prompt original, y consistente con el patrón ya usado en `AffiliateController::expiringToday()` y
`AppointmentController::index()`).

### `User::isFranchise(): bool`

Nuevo método en el modelo, análogo a `isSuperAdmin()`:

```php
public function isFranchise(): bool
{
    return $this->type === 2;
}
```

### `App\Http\Controllers\ReportController`

Un método por reporte + su `.../export`, en `Route::prefix('reports')` dentro del grupo
`auth:sanctum` existente. Cada método:
1. Resuelve el rol: `isSuperAdmin()` → sin restricción; `isFranchise()` → alcance propio; ningún
   otro `type` → `403`. En `unsentCarnets`/`unsentCarnetsExport`, también `403` si es franquicia.
2. Valida filtros con el `FormRequest` correspondiente (inyectado por tipo).
3. Llama a `(new XxxReport())->query($request->validated(), auth()->user())`.
4. Listado: `->paginate($perPage)`, mismo formato `{message, data, meta}` que el resto del proyecto
   (`per_page` limitado a `min($perPage, 100)`, o sin límite si `per_page=all`).
5. Export: `->get()` sin paginar, vía `Maatwebsite\Excel\Facades\Excel::download(...)`.

### `App\Http\Requests\Reports\*Request`

Un `FormRequest` por reporte (`SalesReportRequest`, `BalanceReportRequest`,
`AffiliatesSummaryReportRequest`, `AppointmentsReportRequest`,
`NonRenewedAffiliatesReportRequest`, `UnsentCarnetsReportRequest`), reglas comunes:
- `from`/`to`: `nullable|date_format:Y-m-d`, con validación adicional `to >= from` cuando ambos
  vienen (`withValidator` closure).
- `per_page`: `nullable|in:25,50,100,all`.
- IDs de catálogo (`counselor_id`, `doctor_id`, `city_id`, `department_id`, `franchise_id`):
  `nullable|integer|exists:<tabla>,id`.
- Sigue la convención `400` del proyecto: override de `failedValidation()` (mismo patrón que
  `StoreAffiliateRequest`/`UpdateDoctorRequest`), no el `422` nativo de Laravel — es un módulo
  nuevo y no hay tráfico previo en `422` que proteger.

### Rutas (`routes/api.php`)

Dentro del grupo `auth:sanctum` existente, antes de cualquier `apiResource` que pudiera colisionar
(no aplica aquí, pero se mantiene la convención):

```php
Route::prefix('reports')->group(function () {
    Route::get('sales', [ReportController::class, 'sales']);
    Route::get('sales/export', [ReportController::class, 'salesExport']);

    Route::get('balance', [ReportController::class, 'balance']);
    Route::get('balance/export', [ReportController::class, 'balanceExport']);

    Route::get('affiliates-summary', [ReportController::class, 'affiliatesSummary']);
    Route::get('affiliates-summary/export', [ReportController::class, 'affiliatesSummaryExport']);

    Route::get('appointments', [ReportController::class, 'appointments']);
    Route::get('appointments/export', [ReportController::class, 'appointmentsExport']);

    Route::get('non-renewed-affiliates', [ReportController::class, 'nonRenewedAffiliates']);
    Route::get('non-renewed-affiliates/export', [ReportController::class, 'nonRenewedAffiliatesExport']);

    Route::get('unsent-carnets', [ReportController::class, 'unsentCarnets']);
    Route::get('unsent-carnets/export', [ReportController::class, 'unsentCarnetsExport']);
});
```

---

## 2. Reportes — detalle por reporte

Reglas transversales a los 6 (salvo Carnets, ver su sección): filtros por ID siempre con `=`
(nunca `LIKE`), fechas inclusivas en ambos extremos, `affiliates.stade = 1` salvo excepción
explícita (Reporte 5), orden en base de datos (`orderBy`, nunca `->get()->sortBy()`), paginación
25/50/100/todos con `meta.total` calculado sobre la misma condición `WHERE` que el listado.

### Reporte 1 — Ventas (`reports/sales`)

**Query base:** `affiliates` con la última `renovation` por afiliado (relación
`hasOne(Renovation::class)->ofMany('id', 'max')` en el modelo `Affiliate`, evita subconsulta
correlacionada manual), `join` a `counselors`, `cities`, `agreements`. Filtro fijo:
`stade = 1 AND payment_date IS NOT NULL`.

**Filtros:** `from`/`to` sobre `payment_date` · `franchise_id` (`user_id`, solo super admin ve el
select; franquicia se fuerza a la suya) · `counselor_id` (autocompletar, mínimo 2 caracteres sobre
`CONCAT(name,' ',lastname)`).

**Columnas calculadas:**
- `tipo_venta` = `latestRenovation === null ? 'Nuevo' : 'Renovación'`
- `fecha_desde` = Nuevo → `affiliates.validity`; Renovación → `renovation.date_ini`
- `valor_venta` = Nuevo → `affiliates.value` (valor realmente vendido, puede diferir del catálogo
  `agreements.amount`); Renovación → `renovation.value`

**Columnas tabla/Excel:** Fecha Venta (`payment_date`) · Desde (`fecha_desde`) · Hasta
(`validity_end`) · Afiliación (`validity`) · Asesor (`counselor.name lastname`) · Nombre Afiliado
(`affiliates.name lastname`) · Franquicia (`user.name`) · Tipo Venta (resaltado visual Nuevo vs
Renovación) · Valor Venta (miles con punto, sin decimales) · Ver.

**Orden:** `payment_date DESC, name ASC`.

**Totales (pie de tabla, calculados sobre todo el filtro, no la página):** cantidad y valor de
renovaciones, cantidad y valor de nuevos — query agregada aparte usando la misma condición base.

### Reporte 2 — Cartera (`reports/balance`)

**Query base:** `affiliates` con `counselor`, `stade = 1 AND balance > 0`.

**Filtros:** `counselor_id` (select) · `franchise_id` (solo super admin; franquicia forzada a la
suya).

**Columnas:** Asesor · Nombre Afiliado · Valor Saldo (`$ ` + miles) · Fecha de Ingreso (`validity`)
· Ver. **Fila de total de saldo** (suma de `balance` sobre todo el filtro).

**Orden:** `name ASC`. Paginación por defecto 15 (igual que legacy, distinto al resto).

### Reporte 3 — Resumen de Afiliados, indicadores (`reports/affiliates-summary`)

Sin tabla — 6 tarjetas de conteo + export Excel de esos 6 números.

**Filtros:** `from`/`to` sobre `validity` (**solo se aplican si vienen ambos**) · `city_id` (con
`department_id` como filtro en cascada en el frontend) · `franchise_id`.

**Condición base de titulares (T):** `affiliates WHERE stade = 1` + filtros.

| Indicador | Cálculo |
|---|---|
| Titulares | `count()` de T |
| Titulares activos | T con `validity_end >= hoy` |
| Titulares inactivos | Titulares − activos |
| Beneficiarios | `beneficiaries WHERE affiliate_id IN (T)` |
| Beneficiarios activos | beneficiarios cuyo titular está en T y `validity_end >= hoy` |
| Beneficiarios inactivos | Beneficiarios − activos |

### Reporte 4 — Citas (`reports/appointments`)

**Query base:** `appointments` join `doctors`. Nombre del paciente resuelto en una sola query con
doble `LEFT JOIN` (afiliados y beneficiarios), sin N+1:

```php
Appointment::query()
    ->select('appointments.*')
    ->leftJoin('affiliates', function ($j) {
        $j->on('affiliates.id', '=', 'appointments.afi_code')->where('appointments.type', 1);
    })
    ->leftJoin('beneficiaries', function ($j) {
        $j->on('beneficiaries.id', '=', 'appointments.afi_code')->where('appointments.type', 2);
    })
    ->addSelect([
        'patient_name' => DB::raw("COALESCE(affiliates.name, beneficiaries.name)"),
    ]);
```

**Filtros:** `from`/`to` sobre `date` · `doctor_id` · `franchise_id` (`appointments.user_id`, solo
super admin; franquicia forzada a la suya).

**Columnas:** Nombre (+ sufijo "(Titular)"/"(Beneficiario)" según `type`) · Médico (`name
lastname`) · Ciudad (`doctors.city_id` → `cities.name`, no hay columna `doctors.city` en este
esquema) · Fecha (color: rojo si `< hoy`, azul si `= hoy`, verde si `> hoy`) · Ver.

**Orden:** `date DESC` (el legacy no tenía orden definido).

### Reporte 5 — Sin Renovación (`reports/non-renewed-affiliates`)

**Query base:** `affiliates` join `users` (franquicia), **sin filtro de `stade`** (decisión tomada
con el usuario — ver Contexto), `validity_end <= hoy`.

**Filtros:** `from` → `validity_end BETWEEN :from AND hoy` (el "hasta" es siempre hoy, no hay
segundo campo) · `franchise_id`.

**Columnas:** Hasta (`validity_end`) · Titular (`name lastname`) · Teléfono (`phone`) · Celular
(`movil`) · Franquicia (`user.name`).

**Orden:** `validity_end DESC`.

### Reporte 6 — Carnets No Enviados (`reports/unsent-carnets`)

**Solo super admin — 403 para cualquier otro rol, incluida franquicia.**

**Query base:**
```php
WhatsappMessage::query()
    ->where('type', 'carnet')
    ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
    ->join('affiliates', DB::raw('affiliates.movil'), '=', DB::raw('SUBSTRING(whatsapp_messages.recipient_id, 3)'))
    ->join('users', 'users.id', '=', 'affiliates.user_id')
```
Filtrado en PHP tras traer las filas candidatas (o vía `whereRaw` con `JSON_EXTRACT` si el motor de
BD lo soporta de forma portable): descartar las filas donde `response` sí trae
`messages[0].id` — esas fueron envíos exitosos, no pertenecen a "no enviados". Esta es la
diferencia clave frente al legacy: `deleted=0` ya no distingue nada porque nunca cambia (ver
Contexto).

**Filtros:** `from`/`to` sobre `DATE(created_at)`, **por defecto primer y último día del mes
actual** · `franchise_id`.

**Columnas:** Fecha (`DATE(created_at)`) · Titular · Teléfono · Celular · Franquicia.

**Orden:** `created_at DESC`.

---

## 3. Excel

**Librería nueva:** `maatwebsite/excel` (no hay ninguna instalada hoy — se confirmó con el
usuario). Una clase exportable por reporte en `App\Reports\Exports\` (`FromQuery` + `WithHeadings`
+ `WithMapping` + `WithDrawings` para el logo + `ShouldAutoSize`), construida sobre el mismo
`query()` de la clase de reporte — nunca una query paralela.

Nombre de archivo: `Reporte_<Nombre>_dd-mm-yyyy.xlsx`. Encabezado con logo de Contacto Médico y
cabecera de tabla en negrita. Exporta el filtro completo, no la página visible (bug conocido del
legacy). **Los 6 reportes tienen export**, incluido el de indicadores (Reporte 3, formato simple de
6 filas).

---

## 4. Frontend (Next.js, `frontend-cm`)

- Sección "Reportes" nueva en el sidebar admin, mismo patrón visual que "Administración de
  Contenido" (submenú con las 6 opciones). Visibilidad por rol: oculto para cualquier `type` que no
  sea `1` o `2`; "Carnets No Enviados" oculto para franquicia (`type=2`).
- Una página por reporte bajo `app/(admin)/reports/<slug>/page.tsx`. Filtros viven en
  `searchParams` de la URL; cambiar un filtro vuelve a página 1.
- Tabla paginada reutilizando el componente de tabla/filtros/selects ya existente en el proyecto,
  selector 25/50/100/todos, total de registros visible.
- Botón "Generar reporte" llama al endpoint `.../export` con los mismos filtros de la URL y
  descarga el blob (mismo mecanismo de descarga con token que ya use el proyecto, ej. el de
  `CarnetController`/citas si aplica, o el estándar de fetch+blob del panel).
- Datepickers en pantalla `dd/mm/yyyy`; al API se envía `yyyy-mm-dd`.
- Departamento → Ciudad: select en cascada, reutilizando el patrón ya usado en el formulario
  público de afiliación.
- Asesor: select simple en Reporte 2, autocompletar (mínimo 2 caracteres) en Reporte 1 — si el
  autocompletar no existe hoy como componente, se construye uno reutilizable a partir del patrón de
  select existente.

---

## 5. Testing

`tests/Feature/Reports/` (Pest o PHPUnit, el que use el repo), un archivo por reporte, incluyendo
como mínimo:
- **Ventas:** un afiliado nuevo (sin renovaciones) y uno con varias renovaciones (debe tomar solo
  la última por `id`); totales agregados coinciden con la suma manual esperada.
- **Cartera:** solo aparecen afiliados con `balance > 0`; total de saldo correcto.
- **Resumen de afiliados:** beneficiarios de franquicia A no se cuentan en los indicadores de B.
- **Citas:** una cita de titular (`type=1`) y una de beneficiario (`type=2`) resuelven el nombre
  correcto sin N+1 (assert de cantidad de queries con `DB::enableQueryLog()` o similar).
- **Sin Renovación:** un afiliado con `stade=2` (ya inactivado por el cron) y `validity_end` vencido
  SÍ aparece en el reporte (confirma la decisión de ignorar `stade`).
- **Carnets No Enviados:** un `recipient_id` con prefijo `57` matchea el `movil` sin prefijo; un
  mensaje con `response` conteniendo `messages[0].id` (exitoso) NO aparece; acceso denegado (403)
  para franquicia.
- **En cada reporte del 1 al 5:** con datos de franquicia A y B, la franquicia A solo ve los suyos
  aunque envíe `franchise_id` de B en el request; el super admin ve ambos.
- **Catálogo de asesores:** la franquicia A solo recibe sus propios asesores en el autocompletar.

Ejecutar con `XDEBUG_MODE=off php artisan test --process-isolation` (convención ya documentada del
entorno Windows de este proyecto).

---

## Criterios de aceptación

1. Los 6 reportes accesibles desde el menú "Reportes" en Next.js, visibles solo según la matriz de
   roles (SuperAdmin: 6; Franquicia: 1-5; cualquier otro rol: ninguno).
2. El backend rechaza con `403` cualquier acceso fuera de esa matriz, aunque se llame al endpoint
   directamente (no solo ocultar el menú).
3. Una franquicia, en los reportes 1-5, solo ve datos propios en listado, totales y Excel, aunque
   manipule `franchise_id` en el request. En Carnets No Enviados recibe `403`.
4. El total mostrado = filas del listado sin paginar = filas del Excel, para el mismo filtro
   (misma condición `WHERE` en los tres casos).
5. Totales del Reporte 1 (Ventas) calculados sobre todo el rango filtrado, no la página visible.
6. Reporte 5 incluye afiliados con `stade=2` vencidos (no solo `stade=1`).
7. Reporte 6 clasifica "no enviado" parseando `response`, no `deleted`.
8. Queries sin N+1; índices ya existentes cubren `payment_date`, `validity_end`, `user_id` en
   `affiliates`; `date` en `appointments`; se revisa si `whatsapp_messages(created_at)` necesita
   índice nuevo (no existe hoy) — agregar en la migración de este módulo si el `EXPLAIN` lo
   justifica.
9. Tests de feature cubriendo los casos listados en la sección de Testing, todos en verde con
   `--process-isolation`.
