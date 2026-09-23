# Spec: Auditoría de acciones (`regist_actions`)

**Fecha:** 2026-09-22
**Rama destino:** develop

---

## Contexto

La tabla `regist_actions` ya existe en la base de datos real y tiene registros del sistema
anterior — auditaba creaciones (`I`) y ediciones (`U`) de franquicias, médicos y especialidades.
En este backend, el modelo `RegistAction` y `RegistActionController` existen pero nunca se
implementaron: el controlador está 100% vacío y el `$fillable` del modelo (`action`, `table`) ni
siquiera coincide con las columnas reales (`action_type`, `target_table`). Auditorías previas del
proyecto (`docs/superpowers/specs/2026-08-25-retrofit-dev-standards-backend-design.md`) ya habían
confirmado que es código muerto sin rutas registradas.

El objetivo de este spec es dejar de generar esos registros en el vacío: implementar el guardado
real, ampliando el alcance más allá de lo que auditaba el sistema anterior.

**Alcance decidido con el usuario:**
- Catálogos administrados por el panel (creación y edición): médicos, especialidades, franquicias
  (ya se auditaban antes) + asesores y convenios (nuevos, mismo patrón).
- Afiliados: **no** se audita cada edición (volumen demasiado alto, sin valor) — solo el cambio de
  `stade` (activo ↔ inactivo), sin importar qué rol lo haga. Hoy solo el super admin puede
  disparar ese cambio (regla ya documentada en `CLAUDE.md`), pero ese permiso se va a abrir a más
  roles más adelante — la auditoría no debe depender de quién lo hizo, solo de que `stade` cambió,
  para no requerir tocar este código otra vez cuando cambie el permiso.
- Ninguna tabla del proyecto hace borrado físico salvo unas pocas ya documentadas en `CLAUDE.md`
  (afiliados no está entre ellas) — por eso no existe un tercer caso de "eliminación" que auditar
  en los módulos de este spec.
- Explícitamente fuera de alcance: endpoints de lectura (`index`/`show` de
  `RegistActionController`) — este spec solo cubre que los registros se generen correctamente.
  Consultarlos por ahora es un query directo a la DB.

---

## 1. Base de Datos

### Migración: agregar `user_id` a `regist_actions`

Nueva migración (no se edita la migración ya consolidada — esto es trabajo nuevo, no historial):

```php
Schema::table('regist_actions', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->after('table_id')->constrained('users')->nullOnDelete();
});
```

`nullOnDelete()` en vez de `cascade`: si se borra el usuario que hizo la acción, el rastro de
auditoría debe sobrevivir, no desaparecer con él. `nullable()` porque el servicio de logging es de
uso general — si en el futuro se llama desde un contexto sin usuario autenticado (ej. un comando
de consola), no debe romper.

### Modelo `RegistAction`

Corregir `$fillable` para que coincida con las columnas reales:

```php
protected $fillable = ['action_type', 'target_table', 'table_id', 'user_id'];
```

---

## 2. Backend

### Servicio `App\Services\RegistActionLogger`

Punto único de verdad para escribir en `regist_actions`, siguiendo el mismo patrón que
`WhatsAppClient` e `IdCardLookup` (ver sección "Arquitectura: Servicios y Helpers Reutilizables" de
`CLAUDE.md`). Se inyecta por constructor en cada controlador que lo necesite.

```php
class RegistActionLogger
{
    public function created(string $table, int $id): void;        // action_type = 'I'
    public function updated(string $table, int $id): void;        // action_type = 'U'
    public function statusChanged(string $table, int $id): void;  // action_type = 'E'
}
```

Los tres métodos delegan en un privado que arma la fila:
```php
RegistAction::create([
    'action_type'  => $actionType,
    'target_table' => $table,
    'table_id'     => $id,
    'user_id'      => auth()->id(),
]);
```

**Tres `action_type`, no una columna nueva:** `'I'`/`'U'` ya existen en los datos reales; se agrega
`'E'` (Estado) como tercer valor de la misma columna en vez de una columna aparte de "tipo de
acción" — `action_type` ya es conceptualmente eso, y una query `WHERE action_type = 'E'` resuelve
"quién activó/desactivó X" sin duplicar el concepto en dos columnas.

**Regla de prioridad dentro de un mismo `update()`:** si en la misma petición cambia el campo de
estado (`state`/`stade`) y también otros campos, se loguea solo `'E'` (la señal más importante) —
no se duplica fila con un `'U'` adicional para el resto de campos.

### Integración por controlador

Todos siguen el mismo patrón: inyectar `RegistActionLogger` por constructor, llamar justo después
de persistir el modelo.

| Controlador | `target_table` | `store()` | `update()` |
|---|---|---|---|
| `DoctorController` | `doctors` | `created()` | `state` cambió → `statusChanged()`, si no → `updated()` |
| `SpecialtyController` | `specialties` | `created()` | `state` cambió → `statusChanged()`, si no → `updated()` |
| `UserController` (franquicias) | `users` | `created()` | `state` cambió → `statusChanged()`, si no → `updated()` |
| `CounselorController` | `counselors` | `created()` | `state` cambió → `statusChanged()`, si no → `updated()` |
| `AgreementController` | `agreements` | `created()` | `state` cambió → `statusChanged()`, si no → `updated()` |

El chequeo de "cambió el estado" usa `Model::wasChanged('state')` sobre la instancia ya
actualizada (Eloquent la deja disponible después de `->update()` dentro de la misma request).

### Caso especial: `AffiliateController::update()`

No se toca `store()`. En `update()`, después de `$affiliate->update(...)`:

```php
if ($affiliate->wasChanged('stade')) {
    $this->registActionLogger->statusChanged('affiliates', $affiliate->id);
}
```

Sin chequeo de rol — la condición es únicamente que `stade` haya cambiado de verdad. La regla de
negocio de **quién puede** enviar `stade` en el payload no se toca (sigue en
`AffiliateController::update()`, filtrando el campo para no-super-admins vía `$excludedFields`);
esto solo cubre la auditoría del resultado.

---

## 3. Consideraciones

- **No se audita `store()` de afiliados** ni el resto de sus campos en `update()` — es la tabla de
  mayor volumen del sistema y no aporta valor registrar cada edición de teléfono o dirección.
- **Cambios automáticos de `stade`** (el comando `affiliates:update-expired` y la reactivación en
  `RenovationController`) **no** generan fila de auditoría — no hay un usuario humano detrás de esa
  acción y `auth()->id()` sería `null` en el contexto de consola. Si en el futuro se quiere rastrear
  también esos casos, es una extensión aparte, no parte de este spec.
- **No se agrega `'D'` (borrado)** al `action_type` — ningún módulo cubierto aquí hace borrado
  físico habitual (afiliados no se audita en absoluto y los catálogos solo se desactivan, nunca se
  eliminan desde el panel en el flujo normal). Si apareciera un caso real de borrado a auditar, se
  agrega entonces.
- **Sin endpoint de lectura todavía** — `RegistActionController::index()`/`show()` quedan sin
  implementar; se revisa la tabla directo en la DB mientras no haya un caso de uso concreto en el
  panel para mostrarla.
