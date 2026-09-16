# Retrofit dev-standards — Backend (api-cm) — seguimiento

**Fecha:** 2026-09-16
**Proyecto:** Contacto Médico — Backend (api-cm)
**Tipo:** Spec de seguimiento (insumo para un plan de implementación posterior, no es el plan)
**Skill de referencia:** `.claude/skills/dev-standards/` (actualizado) — cada hallazgo cita la
sección exacta de `references/` que aplica.
**Spec anterior:** [2026-08-25-retrofit-dev-standards-backend-design.md](2026-08-25-retrofit-dev-standards-backend-design.md)

## Resumen y alcance

El spec del 2026-08-25 se implementó en la rama `retrofit-dev-standards-backend` (mergeada a
`develop` en `d29e95b`), más los fixes posteriores `a679716`, `9154898` y `f0a99ab`. Este documento
es una segunda pasada de auditoría (no un retrofit desde cero) para medir qué del spec anterior
quedó realmente resuelto y qué apareció de nuevo, ahora que el proyecto tuvo un ciclo completo de
desarrollo desde entonces.

**Verificado como resuelto — no se repite aquí:**
- Hallazgo 0 (bug de `stade` sin restricción a super admin) — corregido, `esSuperAdmin()` (ahora
  `isSuperAdmin()`) se usa consistentemente en `AffiliateController`, `AffiliateNoteController`,
  `AgreementController`, `AppointmentController`, `DashboardController`, `SettingController`. No se
  encontraron checks `type === 1` inline residuales.
- Hallazgo 1.1 (`BeneficiarySyncService`) — extraído y en uso.
- Hallazgo 2.4 (`RenovationController`) — la ruta ya está restringida a
  `->only(['index', 'store', 'show'])` en `routes/api.php:82`.
- Hallazgo 5.1 (Form Requests) — `StoreAffiliateRequest`/`UpdateAffiliateRequest`,
  `StoreUserRequest`/`UpdateUserRequest` implementados con `failedValidation()` sobrescrito (400).
  `Appointment` se revirtió deliberadamente a 422 nativo (commit `cd806a0`) — decisión documentada
  en `CLAUDE.md` ("split 400/422"), no es una regresión.
- `WhatsAppClient` y los scopes de `Affiliate` (con nombres ya migrados a inglés) siguen siendo el
  único punto de verdad, sin duplicación nueva.
- Grupo B del spec anterior (`CarouselController`, `FormController`, `ModuleController`, etc.) sigue
  sin rutas — no se re-audita aquí, la decisión de producto (implementar o eliminar) sigue pendiente
  y sigue fuera de alcance de un spec de estándares.

**Parcialmente resuelto — sí entra en este spec:**
- Hallazgo 3.3/5.2 (`strict_types` + tipos de parámetro/retorno): `declare(strict_types=1)` ya está
  en todo `app/` (confirmado), pero la segunda mitad — completar tipos explícitos de parámetro y
  retorno en los ~204 métodos pendientes — sigue sin implementarse salvo el caso puntual cerrado en
  `a679716` (`AppointmentController`). Ver Hallazgo 3 abajo.
- Hallazgo 2.3 (cobertura medible): `composer.json` ya tiene script `test:coverage`, pero corre con
  `--min=0` porque, según `CLAUDE.md` (sección Testing), este entorno nunca tuvo PCOV/Xdebug con
  coverage disponible — la cobertura real sigue sin medirse. No es un hallazgo nuevo, sigue abierto
  por la misma razón ya documentada.

---

## Hallazgo 1 (Pilar 5 — Calidad de código): `checkIdCard()` duplicado byte-a-byte

**Archivos:**
[AffiliateController.php:192-216](../../../app/Http/Controllers/AffiliateController.php#L192-L216)
y
[CounselorController.php:228-252](../../../app/Http/Controllers/CounselorController.php#L228-L252)

**Estado actual (verificado):** ambos métodos hacen exactamente lo mismo — normalizan `id_card` con
`preg_replace('/\D/', '', ...)`, excluyen opcionalmente por `ignore_id`, consultan `exists()` sobre
su modelo, y devuelven el mismo shape `{exists, message}`. Solo cambian el modelo (`Affiliate` vs
`Counselor`) y el texto del mensaje ("Documento de identidad" vs "Cédula").

**Por qué es un problema:** `code-quality-checklist.md` — "Sin duplicación de lógica de negocio sin
justificación" y `quality-gates.md` — "Bloques idénticos de más de 5 líneas → extraer a una
función/servicio compartido". Un bug futuro en la normalización (ej. si se decide validar longitud
exacta) tendría que corregirse en 2 lugares.

**Cambio propuesto:** extraer un método reusable, por ejemplo
`App\Support\IdCardLookup::exists(string $modelClass, string $idCard, ?int $ignoreId): bool`, y que
ambos controladores lo llamen, conservando cada uno su propio mensaje de respuesta (el mensaje sí es
legítimamente distinto por dominio, no se fuerza a compartirlo).

**Riesgo:** bajo — es una extracción mecánica de una función pura sin efectos secundarios, cubierta
hoy por 0 tests directos (ver Hallazgo 2).

---

## Hallazgo 2 (Pilar 2 — Testing): controladores vivos sin test

**Estado actual (verificado contra `routes/api.php`, para no repetir el error de testear código
huérfano del spec anterior):**

| Controlador | ¿Tiene ruta registrada? | Riesgo si falla |
|---|---|---|
| `AuthController::login()`/`logout()` | Sí (`routes/web.php`) | Alto — único punto de entrada de autenticación, con el provider `md5-eloquent` dual (MD5 legado + bcrypt) |
| `BeneficiaryController` | Sí, `apiResource` completo (`routes/api.php:79`) | Alto — ver Hallazgo 3 (mass assignment) |
| `WhatsappMessageController`, `UserPropertyController`, `ModuleController`, `RegistActionController`, `FormController`, `FormCounselorController`, `MembershipFormBeneficiaryController`, `CarouselController` | **No** (confirmado, sin cambios desde el spec anterior) | Ninguno vía HTTP — siguen en Grupo B, fuera de alcance |
| `AdminController` | **No** (confirmado: `grep AdminController routes/` no devuelve nada) | Ninguno vía HTTP hoy — sigue huérfano, igual que en el spec anterior (no es un hallazgo nuevo, se reconfirma su estado) |

**Cambio propuesto (solo controladores con ruta viva):**
1. `AuthController` — test Feature que cubra login exitoso con password legado MD5, login exitoso
   con password ya migrado a bcrypt, login fallido, y que la cookie `auth_hint` se crea/borra según
   `CLAUDE.md`. Es la pieza de mayor riesgo del proyecto sin ningún test directo.
2. `BeneficiaryController` — test Feature de `store`/`update`/`destroy`/`index`, incluyendo el caso
   de `affiliate_id` inexistente (422/400 esperado) — útil también como red de seguridad para el
   Hallazgo 3.

**`AdminController` no entra al plan de tests de este spec** por la misma razón que en el spec
anterior: sin ruta registrada, un test Feature fallaría por "route not defined", no por el
comportamiento del controlador. Sigue pendiente la decisión de producto (¿se expone o se elimina?).

---

## Hallazgo 3 (Pilar 5 — Calidad de código): `BeneficiaryController` no usa `validated()`

**Archivo:** [BeneficiaryController.php:36-58,82-112](../../../app/Http/Controllers/BeneficiaryController.php#L36-L58)

**Estado actual (verificado):** `store()` y `update()` corren `Validator::make($request->all(), [...])`
pero luego persisten con `Beneficiary::create($request->all())` / `$beneficiary->update($request->all())`
— ignoran el resultado validado y pasan el array crudo del request directamente a Eloquent.

**Por qué es un problema:** en la práctica el `$fillable` del modelo (`affiliate_id`, `name`,
`id_card`, `bithdate`) coincide con las reglas de validación, así que hoy no hay una vulnerabilidad
de mass assignment explotable — pero es el único controlador del proyecto que no sigue el patrón ya
migrado en `Affiliate`/`User`/`Appointment` (`$request->validated()` o `$validator->validated()`).
Si alguien agrega una columna nueva a `$fillable` sin agregar también su regla de validación, este
controlador empezaría a aceptar campos no validados silenciosamente — los otros no, porque ya pasan
por Form Request o `validated()`.

**Cambio propuesto:** cambiar a `Beneficiary::create($validator->validated())` /
`$beneficiary->update($validator->validated())`. Cambio de una línea en cada método, sin romper
comportamiento actual (mismos campos hoy).

**Nota adicional (menor, mismo archivo):** línea 21-22, el comentario `// Relaciones` / `// El
beneficiario pertenece a un afiliado` es un comentario "banner"/WHAT explícito — antipatrón de
`documentation.md` §"Anti-patrones". El método `affiliate()` ya es autoexplicativo por su nombre y
tipo de retorno; se puede quitar el comentario sin agregar nada en su lugar.

---

## Hallazgo 4 (Pilar 3 — Documentación PHPDoc e idioma)

### 4.1: `AdminController` sin PHPDoc y con comentarios banner/emoji en español

**Archivo:** `app/Http/Controllers/AdminController.php` (líneas 55, 113 y métodos públicos completos)

**Estado actual:** ningún método (`index`, `store`, `show`, `update`, `destroy`) tiene PHPDoc.
Además hay comentarios tipo `// ✅ Contraseña segura` (línea 55) y `// ✅ Reencripta` (línea 113) que
no explican ningún WHY (ya se sabe por el código que se hashea) y usan emoji, que no es parte de la
convención de comentarios del proyecto en ningún otro archivo.

**Cambio propuesto:** si se resuelve la decisión de producto del Hallazgo 2 (implementar la ruta o
eliminar el controlador), aplicar la pasada de documentación como parte de ese trabajo. Si el
controlador se mantiene huérfano indefinidamente, es candidato a eliminarse junto con su vista de
huérfano en vez de documentarse — no tiene sentido invertir en documentar código sin punto de
entrada.

### 4.2: variables en español en `DashboardController`

**Archivo:** [DashboardController.php:101-107](../../../app/Http/Controllers/DashboardController.php#L101-L107)

**Estado actual:** la closure `$mesesPorFranquicia` con parámetro `$totalsPorUsuario` quedó en
español, fuera del alcance de los commits `ad67b0e`/`1cc7dfd` que migraron el resto del código base
a inglés (regla vigente en `CLAUDE.md` §"Reglas Generales" punto 1).

**Cambio propuesto:** renombrar a `$monthsByFranchise`/`$totalsByUser` (o equivalente), sin cambiar
lógica. Cambio mecánico y de bajo riesgo.

### 4.3: `CLAUDE.md` desactualizado — nombres de scopes

**Estado actual (verificado):** `CLAUDE.md` sección "Scopes de vigencia en `Affiliate`" documenta
`scopeActivosVencidos()`, `scopeActivosVencenHoy()`, `scopeInactivosPorVencimiento()`. El código real
(`app/Models/Affiliate.php:93,100,107`) usa `scopeActiveExpired`, `scopeActiveExpiringToday`,
`scopeInactiveByExpiry` — la migración a inglés (mismos commits de 4.2) renombró los scopes pero no
se actualizó la documentación.

**Cambio propuesto:** actualizar esa sección de `CLAUDE.md` con los nombres reales. Es una
corrección de documentación, no de código — pero relevante porque `CLAUDE.md` es la fuente de
verdad que usan los agentes de IA para no reintroducir duplicados.

---

## Hallazgo 5 (Pilar 5 — Calidad de código, menor prioridad): patrón `update()` campo por campo repetido

**Archivos:** `DoctorController.php:251-263`, `CounselorController.php:174-198`,
`AdminController.php:109-115`

**Estado actual:** el patrón `if ($request->filled('x')) $model->x = $request->x;` repetido método a
método (con nombres de campo distintos) en vez de `$model->update($validator->validated())` — el
mismo tipo de deuda que ya se resolvió para `Affiliate`/`User`/`Appointment` en el spec anterior,
pero no se extendió a estos tres.

**Cambio propuesto:** homologar al patrón ya establecido (`validated()` + `update()` con reglas
`sometimes|required` para permitir edición parcial, igual que `UpdateAffiliateRequest`). Prioridad
menor a los Hallazgos 1-3 porque no es un bug activo, solo inconsistencia de estilo entre
controladores similares.

---

## Pilar 1 — Arquitectura

### Sin hallazgos nuevos

Los controladores auditados en esta pasada (`AffiliateController`, `AppointmentController`,
`DoctorController`, `CounselorController`) mantienen métodos individuales bajo ~30 líneas, sin
lógica de negocio no trivial mezclada con HTTP fuera de `CarnetController::generatePdf()` (~110
líneas, justificado por coordenadas de PDF, no urgente). Ningún controller nuevo o tocado desde el
spec anterior introdujo una violación de capas.

## Pilar 4 — Dependencias

### Sin hallazgos

`composer.lock` sigue committeado y sin manipulación sospechosa. Cumple el estándar.

---

## Fuera de alcance de este spec

- No se re-evalúa el Grupo B (controladores sin rutas) — sigue siendo una decisión de producto, no
  de estándares, salvo `AdminController` que se reconfirma en su mismo estado (Hallazgo 4.1).
- No se instala PHPStan/Larastan en esta pasada — el estándar (`quality-gates.md` §6) pide activarlo
  en modo "solo reporta" antes de bloquear, y no hay urgencia nueva que lo justifique frente a los
  Hallazgos 1-3, que son más baratos y de mayor impacto inmediato.
- No se completa la segunda mitad de 3.3/5.2 (tipos explícitos de parámetro/retorno en los ~204
  métodos) en este spec — sigue siendo una pasada grande y mecánica que amerita su propio plan
  dedicado, no mezclado con los hallazgos puntuales de esta ronda.

## Siguiente paso

Este documento es el spec de hallazgos de seguimiento. El plan de implementación (orden de
ejecución, TDD, checkpoints) se escribe en `docs/superpowers/plans/`, en este orden sugerido:
Hallazgo 2 (tests de `AuthController`/`BeneficiaryController`) primero, para tener red de seguridad
antes de tocar `BeneficiaryController` en el Hallazgo 3; luego Hallazgo 1 (extracción de
`checkIdCard`) y Hallazgo 5 (homologar `update()`), que son mecánicos y de bajo riesgo; los
Hallazgos 4.1-4.3 (documentación) pueden ir en paralelo por ser cambios aislados sin dependencia de
los anteriores.
