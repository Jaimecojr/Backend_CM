# Retrofit completo dev-standards — Backend (api-cm)

**Fecha:** 2026-08-25
**Proyecto:** Contacto Médico — Backend (api-cm)
**Tipo:** Spec de retrofit completo (insumo para un plan de implementación posterior, no es el plan)
**Skill de referencia:** `.claude/skills/dev-standards/` — cada hallazgo cita la sección exacta de `references/` que aplica.

## Resumen y alcance

El proyecto **no está en producción todavía**, así que este retrofit no sigue el modo incremental
por defecto de `dev-standards` ("congela la regla para código nuevo, no toques lo viejo") —
decisión explícita del usuario: aplicar el estándar completo a todo el código existente ahora,
mientras es barato hacerlo.

**No se repiten aquí los hallazgos ya resueltos** por `2026-08-07-revision-arquitectura-backend`
(spec/plan previos, mergeados en `45f4665`, commit de docs `78171dd`). Ya están hechos y no se
reabren:
- Bug del código de idioma `es`→`es_CO` en `CarnetController`.
- Servicio `App\Services\WhatsAppClient` centralizando el envío WhatsApp (antes duplicado en 3
  controladores).
- `User::esSuperAdmin()` reemplazando las 8 verificaciones inline `type !== 1`.
- Validación de `movil` alineada a `nullable|digits:10` en `AffiliateController`/`UserController`.
- `AuthController` extraído de los closures de `routes/web.php`.
- Scopes de vigencia `Affiliate::scopeActivosVencidos()` / `scopeActivosVencenHoy()` /
  `scopeInactivosPorVencimiento()`.
- Tests Feature iniciales para `AffiliateController`, `AppointmentController`, `CarnetController`,
  `WhatsAppClient`.

Este spec cubre lo que **queda** de los 5 pilares de `dev-standards` tras ese trabajo previo, con
alcance completo (no solo "los módulos más riesgosos").

**Verificación de lo "ya resuelto":** antes de excluirlo de este spec, se auditó con evidencia de
código (no solo confiando en el commit) que los 6 cambios de la lista anterior funcionan
correctamente. 5 de 6 quedaron bien; el sexto (verificación de super admin) reveló un bug activo
real que no formaba parte de esos 8/9 usos migrados — ver Hallazgo 0.

---

## Hallazgo 0 (bug activo): `AffiliateController::update()` no restringe el cambio de `stade` a super admin

**Archivo:** [app/Http/Controllers/AffiliateController.php](../../../app/Http/Controllers/AffiliateController.php) (`update()`, líneas ~153-205)

**Estado actual (verificado):** `update()` valida `'stade' => 'nullable|integer'` y lo persiste con
`$affiliate->update($request->only([..., 'stade', ...]))` sin ninguna verificación de
`$request->user()->esSuperAdmin()` antes de aceptar ese campo.

**Por qué es un problema:** `CLAUDE.md` documenta explícitamente ("Regla de acceso para cambio
manual de stade"): *"Solo el super administrador... puede cambiar el `stade` de un afiliado de
forma manual desde la interfaz. Ningún endpoint de actualización de afiliados debe permitir que un
rol distinto al super admin modifique `stade` directamente."* Hoy, cualquier usuario autenticado
(asesor, franquicia) que llame `PATCH /api/affiliates/{id}` con `stade` en el payload puede
activar/inactivar un afiliado sin pasar por el cron de vencimiento ni por una renovación — esto
afecta directamente lógica de negocio con dinero de por medio (comisiones, vigencias). No hay
ningún test que cubra este escenario con un usuario no-admin; el test existente
(`test_update_no_reactiva_el_afiliado_solo_por_editarlo`) solo prueba que un **admin** no reactiva
por accidente, no que un no-admin sea bloqueado.

**Cambio propuesto:** en `update()`, si el request incluye `stade` y `!$request->user()->esSuperAdmin()`,
remover `stade` del array antes de persistir (o devolver 403 si se prefiere explícito en vez de
ignorar el campo silenciosamente — decidir en el plan de implementación cuál de los dos
comportamientos espera hoy el frontend, para no romper un flujo que dependa de uno u otro).

**Riesgo de no resolverlo:** ninguno adicional al que ya existe hoy en producción — es un bug activo,
no una regresión que introduzca este retrofit. Prioridad alta por ser un problema de autorización
real, no solo de estilo/arquitectura.

**Nota de entorno de testing (no es un hallazgo de código):** en Windows, correr `php artisan test`
con Xdebug activo produce un segfault en `WhatsAppClientTest::test_enviar_plantilla_maneja_error_de_red`
(la excepción de red forzada dentro de `Http::fake()` interactúa mal con el step debugger). Usar
`XDEBUG_MODE=off php artisan test` — con eso el suite completo corre limpio (107 tests, 264
assertions, 0 fallos, verificado en esta sesión).

---

## Pilar 1 — Arquitectura (`references/architecture.md`)

### Hallazgo 1.1: Lógica de sincronización de beneficiarios mezclada en el controlador

**Archivo:** [app/Http/Controllers/AffiliateController.php](../../../app/Http/Controllers/AffiliateController.php) (`store()`, `update()`)

**Estado actual:** `store()`/`update()` validan el afiliado, lo persisten, y además recorren el
array `beneficiaries` del request haciendo `create`/`update`/`delete` según corresponda — todo
dentro del mismo método del controlador, junto con la validación y el armado de la respuesta JSON.

**Por qué es un problema:** es exactamente el criterio de `architecture.md` §3 para "sí necesita
capa de Aplicación" — hay lógica de sincronización multi-paso (decidir qué beneficiarios crear,
cuáles actualizar, cuáles borrar) que no es un CRUD simple. Hoy no se puede testear la lógica de
sincronización sin pasar por HTTP completo, ni reusarla si en el futuro se necesita sincronizar
beneficiarios desde otro punto de entrada (ej. una importación masiva).

**Cambio propuesto:** extraer `App\Services\BeneficiarySyncService` con un método
`sync(Affiliate $affiliate, array $beneficiariosRequest): void` que encapsule la lógica de
create/update/delete. `AffiliateController` pasa a inyectarlo y llamarlo, sin conocer el detalle de
la sincronización.

**Riesgo:** medio — es el flujo de mayor tráfico del panel (alta y edición de afiliados); requiere
tests Feature que cubran los 3 casos (agregar beneficiario nuevo, editar uno existente, quitar uno)
antes de considerar el refactor terminado.

### Hallazgo 1.2: Ningún controlador pequeño tiene capa de Servicio — y está bien así

**Evidencia:** `AgreementController`, `SpecialtyController`, `CityController`, `DepartmentController`,
`CarouselController`, etc. son CRUDs simples sin reglas de negocio no triviales.

**Decisión (no es un hallazgo a corregir):** según `architecture.md` §3, estos módulos **no**
necesitan `Service`/`Repository` — introducir esas capas sería sobre-ingeniería. Se deja constancia
explícita para que el plan de implementación no intente "parejar" artificialmente estos
controladores con los que sí tienen Service.

---

## Pilar 2 — Testing (`references/testing.md`)

### Hallazgo 2.1: Convención de ubicación — ya cumple el estándar, formalizar en `CLAUDE.md`

**Estado actual:** `tests/Unit/` + `tests/Feature/` (convención de Laravel) ya es exactamente la
convención "carpeta espejo" que pide `dev-standards`. **No requiere ningún cambio estructural.**

**Acción:** agregar una línea explícita en `CLAUDE.md` (sección nueva "Testing") declarando esta
convención como la elegida, para que quede documentada y no se mezcle con colocación en el futuro
(regla de `testing.md`: "elige UNA convención... y documéntala").

### Hallazgo 2.2: controladores sin ningún test — pero no todos son código vivo

**Evidencia verificada (investigación exhaustiva de rutas, `$fillable` y factories):** de los ~20
controladores sin test, solo **11 son código vivo y realmente testeable por HTTP**. Los otros
**9 son código muerto o roto** — no hay ruta registrada en `routes/api.php`/`web.php`, así que
ningún test Feature real puede ejercitarlos, y en varios casos ni siquiera compilarían contra el
schema real. Separar esto era necesario antes de escribir el plan: no tiene sentido "agregar tests"
a un endpoint que no existe.

**Grupo A — código vivo, sí entra al plan de tests:**

| Controlador | Complejidad | Prioridad | Nota |
|---|---|---|---|
| `RenovationController` | Alta (dinero, reactivación de `stade`) | 1 | Solo `index`/`store`/`show` están implementados — ver Hallazgo 2.4 |
| `UserController` (CRUD completo, solo tiene test de `movil`) | Media-alta | 1 | — |
| `DoctorController` | Media (usado por sitio público y panel) | 2 | — |
| `CounselorController` | Media | 2 | — |
| `AgreementController` | Media (permisos super admin en store/update) | 2 | — |
| `AffiliateNoteController` | Media (permisos super admin en destroy) | 2 | — |
| `MembershipFormController` (admin: index/show/destroy/convert) | Media | 2 | `store` es la ruta pública, no admin |
| `SettingController` | Baja (singleton, sin `store`) | 3 | Sin factory — el test crea la fila directo |
| `SpecialtyController` | Baja (catálogo, con CRUD completo) | 4 | — |
| `CityController` | Baja | 4 | Solo tiene `getByDepartment`, no CRUD |
| `DepartmentController` | Baja | 4 | Solo tiene `index`, no CRUD |

**Grupo B — código muerto o roto, NO entra al plan de tests (requiere decisión del usuario primero):**

| Controlador | Estado real |
|---|---|
| `CarouselController` | Sin rutas registradas. `$fillable` (`original`,`rename`) no coincide con las columnas reales (`photo`,`photo_rename`) — un `Carousel::create()` fallaría con `QueryException`. |
| `FormController` | Sin rutas. Modelo sin `$fillable` → mass assignment bloqueado por defecto de Eloquent. |
| `FormCounselorController` | Sin rutas. Mismo problema de mass assignment que `Form`. |
| `ModuleController` | Sin rutas. `$fillable` (`order`) no coincide con la columna real (`sort_order`). |
| `RegistActionController` | Sin rutas. `$fillable` (`action`,`table`) no coincide con las columnas reales (`action_type`,`target_table`). |
| `UserPropertyController` | Sin rutas. Controlador 100% stub vacío. |
| `WhatsappMessageController` | Sin rutas. Controlador 100% stub vacío (el envío/recepción real de WhatsApp pasa por `WhatsAppWebhookController`, que es otro controlador y sí tiene rutas). |
| `MembershipFormBeneficiaryController` | Sin rutas. Controlador 100% stub vacío. |
| `AdminController` | **Único caso distinto**: SÍ tiene lógica completa y funcional (CRUD real con validación), pero no tiene ninguna ruta registrada — código huérfano, no un stub vacío. |

**Por qué es un problema (Grupo A):** sin estos tests, cualquier retrofit de los pilares 1/3/4/5
sobre estos archivos (ej. agregar `declare(strict_types=1)`) no tiene forma automatizada de
confirmar que no rompió nada.

**Por qué el Grupo B queda fuera del plan de tests (no es indecisión, es un hecho verificado):**
escribir un test Feature contra una ruta que no existe no prueba nada — el test fallaría con
"route not defined", no con el comportamiento del controlador. Antes de poder testear estos 9,
alguien tiene que decidir si se implementan (rutas + lógica + corregir los `$fillable` rotos) o se
eliminan como scaffolding sin uso — **esa decisión no es parte de este spec de retrofit**, es una
decisión de producto que se le plantea al usuario por separado.

**Cambio propuesto (solo Grupo A):** un test Feature por controlador cubriendo al menos `index` con
filtro, `store` con datos válidos e inválidos, `update` (donde exista), `destroy` (donde exista).
Prioridad 1 y 2 primero; prioridad 3/4 pueden ser tests más delgados (happy path + 1 caso de error)
dado que son catálogos con poca o ninguna lógica de negocio.

### Hallazgo 2.4: `RenovationController` — rutas de `update`/`destroy` registradas pero no implementadas

**Evidencia:** `routes/api.php` registra `Route::apiResource('renovations', RenovationController::class)`,
que crea las 7 rutas estándar incluyendo `PUT/PATCH /api/renovations/{id}` y
`DELETE /api/renovations/{id}`. El controlador solo define `index()`, `store()` y `show($id)`.

**Por qué es un problema:** llamar `PUT`/`DELETE` sobre este endpoint hoy produce un error fatal de
PHP (`Call to undefined method`), no un 404/405 limpio — un cliente HTTP que intente estas
operaciones recibe un 500 en vez de un error claro de "método no permitido". Dado que las
renovaciones documentadas en `CLAUDE.md` nunca se editan ni eliminan (son un registro histórico),
es probable que esto sea intencional en el negocio, pero la implementación actual expone rutas que
no deberían existir.

**Cambio propuesto:** cambiar `Route::apiResource(...)` por
`Route::apiResource('renovations', RenovationController::class)->only(['index', 'store', 'show'])`,
que hace que Laravel devuelva 404 limpio en `PUT`/`DELETE` en vez de un fatal error. Confirmar con
un test que ambos verbos ya no producen 500.

**Riesgo:** ninguno — restringe rutas que nunca funcionaron, no cambia comportamiento existente
válido.

### Hallazgo 2.3: Sin configuración de cobertura en `phpunit.xml`

**Estado actual:** no hay bloque `<coverage>`, ni driver (Xdebug/PCOV) configurado, ni script de
coverage en `composer.json`.

**Por qué es un problema:** sin esto, el objetivo de cobertura 85-100% de `testing.md` no se puede
verificar — solo se sabe "hay tests o no hay", no cuánto código ejecutan.

**Cambio propuesto:**
1. Instalar PCOV (`composer require --dev pcov/clobber` o habilitar la extensión PHP) — más rápido
   que Xdebug para solo-cobertura.
2. Agregar a `phpunit.xml`:
   ```xml
   <source>
       <include>
           <directory>app</directory>
       </include>
   </source>
   ```
3. Agregar script `composer.json`: `"test:coverage": "@php artisan test --coverage --min=85"`.

**Riesgo:** bajo — es configuración, no cambia comportamiento. El `--min=85` puede fallar al
principio hasta que los hallazgos 2.2 se resuelvan; se activa como gate de CI solo cuando la
cobertura real ya lo permita.

---

## Pilar 3 — Documentación PHPDoc (`references/documentation.md`)

### Hallazgo 3.1: ~40% de métodos públicos sin PHPDoc, y la mayoría de los que existen son WHAT

**Evidencia verificada:** en la muestra auditada (`AffiliateController`, `AppointmentController`,
`UserController`, `AuthController`), la mayoría de los bloques `/** */` existentes repiten el
nombre del método (`/** Mostrar todos los afiliados */` sobre `index()`) en vez de explicar una
decisión de negocio no obvia.

**Excepciones ya bien hechas (usar como referencia de estilo):**
`AffiliateController::byIdCard()`/`::publicStatus()`, el comentario inline de `auth_hint` en
`AuthController::login()`, y `WhatsAppClient::enviarPlantilla()`/`::enviarTexto()`.

**Cambio propuesto:** pasada completa por los 31 controladores. Regla operativa: si el método no
tiene ninguna decisión de negocio no obvia (ej. un `destroy()` que solo borra), **no forzar** un
PHPDoc — `documentation.md` permite omitirlo cuando el nombre ya es autoexplicativo. Priorizar
donde SÍ hay algo no obvio que explicar: por qué `AffiliateController::update()` nunca reactiva
`stade`, por qué `AppointmentController::index()` normaliza `owner` en vez de leerlo de la BD, por
qué `RenovationController::store()` reactiva el afiliado como respaldo de backend.

### Hallazgo 3.2: Idioma — español, no inglés (excepción documentada, no un hallazgo a corregir)

`CLAUDE.md` línea 366 exige español en comentarios, PHPDoc y mensajes JSON — contradice la regla
genérica de `documentation.md` ("todo en inglés, sin excepción"). **Decisión:** para este proyecto,
español es la convención vigente y documentada; el retrofit **no traduce nada existente ni exige
inglés en código nuevo**. Se deja constancia explícita en el plan de implementación para que ningún
paso intente "corregir" el idioma.

### Hallazgo 3.3: `declare(strict_types=1)` — 0 de 62 archivos

**Evidencia:** `grep -rl "declare(strict_types" app` → 0 resultados.

**Por qué es un problema:** sin esto, PHP hace coerción de tipos silenciosa (ej. un string numérico
donde se esperaba un `int`), lo que puede esconder bugs de validación — relevante en un proyecto
con mucho manejo de dinero (`value`, `balance`, `commission`) y de fechas.

**Cambio propuesto:** agregar `declare(strict_types=1);` como primera línea de los 62 archivos de
`app/`, y completar tipos de parámetro/retorno donde falten (ej. `show($id)` → `show(int $id)`,
métodos sin tipo de retorno declarado). Es mecánico pero **no es sin riesgo**: activar
`strict_types` puede exponer un `TypeError` donde antes había coerción silenciosa (ej. un test o
un caller que pasa un string donde el método espera `int`). Por eso este hallazgo depende de que
2.2 (cobertura de tests) avance primero — sin tests, un `TypeError` nuevo se descubre en producción,
no en CI.

**Orden recomendado:** aplicar archivo por archivo, no en un solo commit masivo, corriendo
`php artisan test` después de cada archivo modificado.

**Estado real al cierre de la rama (2026-08-25):** solo se aplicó la línea
`declare(strict_types=1);` en los archivos de `app/`. La parte de "completar tipos de
parámetro/retorno donde falten" descrita arriba **no se implementó** — son ~204 métodos entre
controladores, requests y servicios, y hacerlo con el mismo cuidado archivo por archivo (con
`php artisan test` después de cada uno) excede el alcance de esta rama. Queda pendiente como
trabajo futuro; ver también Hallazgo 5.2 abajo.

---

## Pilar 4 — Dependencias (`references/dependency-management.md`)

### Sin hallazgos

`composer.lock` está committeado, sin manipulación sospechosa (2 commits en su historia completa,
ambos de agregar dependencias, ninguno de borrado/regeneración). Cumple el estándar. No se requiere
ninguna acción.

---

## Pilar 5 — Calidad de código (`references/code-quality-checklist.md`)

### Hallazgo 5.1: Validación duplicada entre `store()` y `update()` en varios controladores

**Evidencia:** `AffiliateController`, `AppointmentController`, `UserController` repiten
prácticamente el mismo array de reglas de `Validator::make()` en ambos métodos.

**Por qué es un problema:** el spec previo (`2026-08-07`) descartó introducir Form Requests de
forma masiva porque en ese momento el alcance era acotado a hallazgos puntuales. Con alcance de
retrofit completo, esto ya justifica una capa de `FormRequest` en los controladores donde la
duplicación es real — no en catálogos simples de una sola regla (`architecture.md` §3, criterio de
"complejidad del módulo").

**Cambio propuesto:** `StoreAffiliateRequest`/`UpdateAffiliateRequest`,
`StoreAppointmentRequest`/`UpdateAppointmentRequest`, `StoreUserRequest`/`UpdateUserRequest`. Cada
uno centraliza las reglas hoy repetidas; el controlador recibe el Request ya validado.

**Riesgo:** medio — cambia la forma en que se reporta un error de validación (Laravel devuelve 422
por defecto en FormRequest; los controladores actuales devuelven 400 con `Validator::make()` según
`CLAUDE.md`). **Debe preservarse el código 400 actual** (hay tests y comportamiento de frontend que
dependen de él) — el FormRequest necesita sobrescribir `failedValidation()` para mantener 400 en vez
del 422 por defecto. Esto se documenta como restricción explícita para el plan de implementación.

### Hallazgo 5.2: Tipado de parámetros/retorno inconsistente

Cubierto junto con 3.3 (misma pasada mecánica: `strict_types` + tipos de parámetro/retorno se
hacen juntos, archivo por archivo).

**Estado real al cierre de la rama (2026-08-25):** NO implementado, igual que 3.3 arriba. Solo se
agregó `declare(strict_types=1)`; los métodos siguen sin tipos de parámetro/retorno explícitos
(ej. `show($id)` en vez de `show(int $id): JsonResponse`). Sigue abierto para una pasada futura.

---

## Fuera de alcance de este spec

- No se reevalúa nada ya resuelto por el spec/plan de arquitectura del 2026-08-07 (ver Resumen).
- No se propone ORM/patrón repositorio adicional sobre Eloquent — sigue siendo sobre-ingeniería
  para el tamaño del proyecto (mismo criterio del spec previo, sigue vigente).
- No se propone traducir comentarios/mensajes existentes a inglés (ver Hallazgo 3.2).

## Siguiente paso

Este documento es el spec de hallazgos. El plan de implementación (orden de ejecución, tarea por
tarea con TDD, checkpoints de verificación) se escribe en un documento separado en
`docs/superpowers/plans/`, siguiendo el mismo orden de prioridad usado en este spec: primero
cobertura de tests (2.2) para poder aplicar con seguridad `strict_types`/tipado (3.3/5.2) y las
extracciones de Servicio (1.1) y Form Requests (5.1) después.
