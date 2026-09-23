# Módulo de Reportes — Backend (Laravel) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel API for the 6 legacy reports (Ventas, Cartera, Resumen de Afiliados, Citas, Sin Renovación, Carnets No Enviados), each with a paginated JSON listing endpoint and an `.xlsx` export endpoint, scoped by franchise and permission-checked per the approved spec.

**Architecture:** One query-building class per report in `App\Reports\`, shared by the listing endpoint (`ReportController`), the export endpoint (`ReportExportController`), and its Excel export class (`App\Reports\Exports\`) — never two copies of the same `WHERE`. A shared `AppliesFranchiseScope` trait centralizes "franchise sees only its own rows, ignore any client-sent franchise id" across the 5 reports that need it.

**Tech Stack:** Laravel 12 / PHP 8.2, `maatwebsite/excel` (new dependency) for `.xlsx` export, PHPUnit (class-based, `RefreshDatabase`, SQLite in-memory for tests / MySQL in production), Sanctum `auth:sanctum` guard (unchanged).

**Spec:** `docs/superpowers/specs/2026-09-22-reports-module-design.md`

## Global Constraints

- Every report's listing, its `meta.total`, and its Excel export run through the exact same `App\Reports\*Report::query()`/equivalent method — never a second, slightly-different `WHERE`.
- A franchise user (`type=2`) never sees another franchise's rows in list, totals, or Excel, even if it sends a different `franchise_id` in the request — enforced via `AppliesFranchiseScope`, applied in reports 1–5 (not report 6, super-admin only).
- Filters by ID always use `=`, never `LIKE`.
- Date range filters are inclusive on both ends.
- Every new `FormRequest` overrides `failedValidation()` to return `400` (this project's convention for new modules — see `CLAUDE.md`, "Código HTTP en fallos de validación").
- Report 5 (Sin Renovación) does **not** filter by `stade` — confirmed decision, see spec Contexto.
- Report 6 (Carnets No Enviados) determines "no enviado" by parsing `whatsapp_messages.response` for `messages[0].id`, never by the `deleted` column (which nothing in this codebase ever sets to `1`).
- Any role other than `type=1` (super admin) or `type=2` (franchise) gets `403` on all 6 reports; franchise additionally gets `403` on report 6.
- All SQL must run on both SQLite (`phpunit.xml`: `DB_CONNECTION=sqlite`, `:memory:`) and MySQL (production) — no `CONCAT()`, prefer `SUBSTR()` (works on both) over `SUBSTRING()`, and branch on `config('database.default') === 'sqlite'` only where genuinely needed (mirror `DashboardController`'s existing pattern).
- Tests run with `XDEBUG_MODE=off php artisan test --process-isolation` (documented Windows-local requirement, unrelated to this module but applies to every task here).

## Review Focus

- `per_page=all` on a report with zero matching rows must not divide by zero when computing `meta.last_page` — covered in Task 6's pagination test.
- A franchise sending another franchise's `franchise_id` must be blocked in **every** report from 1 to 5, not just the first one implemented — each of Tasks 6, 7, 8, 9, 10 repeats this exact test against its own table/column.
- Report 1 classifies "Renovación" by `latestRenovation !== null`, not by `renovation.date_payment` (legacy's field) — an affiliate whose only renovation has an old `date_payment` must still show as "Renovación", covered in Task 6.
- Report 6's phone-substring join must not error or false-match when `recipient_id` has an unexpected prefix (no affiliate has that local number) — it should simply not appear, covered in Task 11.
- A `type=3` (or any non-1/non-2) authenticated user must get `403`, not silently fall into the franchise branch — the check lives once in `AuthorizesReportAccess` (Task 6) and is exercised by both its `franchiseAllowed: true` branch (Task 6's `sales` test) and its `franchiseAllowed: false` branch (Task 11's `unsentCarnets` tests); every other report task calls the same trait method, so it does not get its own duplicate 403 test.

---

## File Structure

```
app/
  Models/
    User.php                          (modify: + isFranchise())
    Affiliate.php                     (modify: + latestRenovation())
  Reports/
    Concerns/
      AppliesFranchiseScope.php       (new, Task 6)
    SalesReport.php                   (new, Task 6)
    BalanceReport.php                 (new, Task 7)
    AffiliatesSummaryReport.php       (new, Task 8)
    AppointmentsReport.php            (new, Task 9)
    NonRenewedAffiliatesReport.php    (new, Task 10)
    UnsentCarnetsReport.php           (new, Task 11)
    Exports/
      SalesReportExport.php           (new, Task 6)
      BalanceReportExport.php         (new, Task 7)
      AffiliatesSummaryReportExport.php (new, Task 8)
      AppointmentsReportExport.php    (new, Task 9)
      NonRenewedAffiliatesReportExport.php (new, Task 10)
      UnsentCarnetsReportExport.php   (new, Task 11)
  Http/
    Controllers/
      Concerns/
        AuthorizesReportAccess.php    (new, Task 6) — shared 403 check, used by both controllers below
      ReportController.php            (new, Task 5; modified through Task 11) — listing + catalogs
      ReportExportController.php      (new, Task 6; modified through Task 11) — exports only
    Requests/
      Reports/
        SalesReportRequest.php        (new, Task 6)
        BalanceReportRequest.php      (new, Task 7)
        AffiliatesSummaryReportRequest.php (new, Task 8)
        AppointmentsReportRequest.php (new, Task 9)
        NonRenewedAffiliatesReportRequest.php (new, Task 10)
        UnsentCarnetsReportRequest.php (new, Task 11)
database/
  factories/
    CounselorFactory.php              (new, Task 4)
    AgreementFactory.php              (new, Task 4)
    RenovationFactory.php             (new, Task 4)
    WhatsappMessageFactory.php        (new, Task 4)
    CityFactory.php                   (new, Task 4)
    BeneficiaryFactory.php            (new, Task 4)
public/
  images/reports/logo.png             (new, Task 6 — copied from frontend-cm)
routes/
  api.php                             (modify: + reports group, Tasks 5–11)
tests/Feature/Reports/
  UserIsFranchiseTest.php             (new, Task 2)
  AffiliateLatestRenovationTest.php   (new, Task 3)
  ReportFactoriesSmokeTest.php        (new, Task 4)
  CounselorsCatalogTest.php           (new, Task 5)
  SalesReportTest.php                 (new, Task 6)
  BalanceReportTest.php               (new, Task 7)
  AffiliatesSummaryReportTest.php     (new, Task 8)
  AppointmentsReportTest.php          (new, Task 9)
  NonRenewedAffiliatesReportTest.php  (new, Task 10)
  UnsentCarnetsReportTest.php         (new, Task 11)
CLAUDE.md                             (modify: + Reportes section, Task 12)
```

---

### Task 1: Install `maatwebsite/excel`

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/excel.php` (published by the package)

**Interfaces:**
- Produces: `Maatwebsite\Excel\Facades\Excel` and the `Maatwebsite\Excel\Concerns\*` interfaces (`FromCollection`, `FromArray`, `WithHeadings`, `WithMapping`, `ShouldAutoSize`, `WithDrawings`), used starting in Task 6.

- [ ] **Step 1: Install the package**

Run: `composer require maatwebsite/excel`

If composer reports a version conflict against `laravel/framework: ^12.0`, run
`composer require maatwebsite/excel:^3.1 --with-all-dependencies` instead — by the time this
plan is executed, `maatwebsite/excel` ^3.1's later tags support Laravel 12.

- [ ] **Step 2: Publish the config**

Run: `php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config`

Expected: creates `config/excel.php`.

- [ ] **Step 3: Verify the package is registered**

Run: `php artisan tinker --execute="echo interface_exists(\Maatwebsite\Excel\Concerns\FromCollection::class) ? 'ok' : 'fail';"`

Expected output: `ok`

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock config/excel.php
git commit -m "chore(reports): install maatwebsite/excel for report exports"
```

---

### Task 2: `User::isFranchise()`

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Reports/UserIsFranchiseTest.php`

**Interfaces:**
- Produces: `User::isFranchise(): bool` — `true` only when `type === 2`. Consumed by every report task's permission check from Task 5 onward.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIsFranchiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_es_franquicia_es_verdadero_solo_para_type_2(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $franchise = User::factory()->create(['type' => 2]);
        $other     = User::factory()->create(['type' => 3]);

        $this->assertFalse($admin->isFranchise());
        $this->assertTrue($franchise->isFranchise());
        $this->assertFalse($other->isFranchise());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=UserIsFranchiseTest`
Expected: FAIL — `Call to undefined method App\Models\User::isFranchise()`

- [ ] **Step 3: Implement**

In `app/Models/User.php`, right after `isSuperAdmin()`:

```php
    // Franchise: type = 2. Sees only its own affiliates/counselors/appointments
    // across the reports module — see App\Reports\Concerns\AppliesFranchiseScope.
    public function isFranchise(): bool
    {
        return $this->type === 2;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=UserIsFranchiseTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/User.php tests/Feature/Reports/UserIsFranchiseTest.php
git commit -m "feat(reports): add User::isFranchise() for report permission checks"
```

---

### Task 3: `Affiliate::latestRenovation()` relation

**Files:**
- Modify: `app/Models/Affiliate.php`
- Test: `tests/Feature/Reports/AffiliateLatestRenovationTest.php`

**Interfaces:**
- Consumes: existing `Affiliate::renovations()` hasMany.
- Produces: `Affiliate::latestRenovation()` — a `hasOne` "one of many" relation resolving to the `Renovation` with the highest `id` for that affiliate, or `null` if none exists. Consumed by `SalesReport` (Task 6).

Depends on `Renovation::factory()`, added in Task 4 — write this task's test using direct
`Renovation::create()` instead, since it doesn't need the full factory.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Renovation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateLatestRenovationTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_null_cuando_no_hay_renovaciones(): void
    {
        $affiliate = Affiliate::factory()->create();

        $this->assertNull($affiliate->latestRenovation);
    }

    public function test_devuelve_la_renovacion_con_mayor_id(): void
    {
        $affiliate = Affiliate::factory()->create();

        $first  = Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2025-01-01',
            'date_end'     => '2025-12-31',
            'date_payment' => '2025-01-01',
            'value'        => 100000,
        ]);
        $second = Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2026-01-01',
            'date_end'     => '2026-12-31',
            'date_payment' => '2026-01-02',
            'value'        => 120000,
        ]);

        $this->assertTrue($second->id > $first->id);
        $this->assertSame($second->id, $affiliate->fresh()->latestRenovation->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=AffiliateLatestRenovationTest`
Expected: FAIL — `latestRenovation` undefined property/relation.

- [ ] **Step 3: Implement**

In `app/Models/Affiliate.php`, right after `renovations()`:

```php
    // The single most recent renovation (highest id), or null if the
    // affiliate has never renewed. Used by the sales report to classify
    // a row as "Nuevo" vs "Renovación" without a correlated subquery.
    public function latestRenovation()
    {
        return $this->hasOne(Renovation::class)->ofMany('id', 'max');
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=AffiliateLatestRenovationTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Affiliate.php tests/Feature/Reports/AffiliateLatestRenovationTest.php
git commit -m "feat(reports): add Affiliate::latestRenovation() relation"
```

---

### Task 4: Shared test factories for the Reports module

**Files:**
- Create: `database/factories/CounselorFactory.php`
- Create: `database/factories/AgreementFactory.php`
- Create: `database/factories/RenovationFactory.php`
- Create: `database/factories/WhatsappMessageFactory.php`
- Create: `database/factories/CityFactory.php`
- Create: `database/factories/BeneficiaryFactory.php`
- Test: `tests/Feature/Reports/ReportFactoriesSmokeTest.php`

**Interfaces:**
- Produces: `Counselor::factory()`, `Agreement::factory()`, `Renovation::factory()`,
  `WhatsappMessage::factory()`, `City::factory()`, `Beneficiary::factory()` — none of these existed
  before (verified: only `UserFactory`, `DoctorFactory`, `AppointmentFactory`, `AffiliateFactory`
  existed). Consumed by every report test task from Task 5 onward — `Beneficiary::factory()`
  specifically by Tasks 8 and 9.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Agreement;
use App\Models\Beneficiary;
use App\Models\City;
use App\Models\Counselor;
use App\Models\Renovation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFactoriesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_nuevas_factories_crean_registros_validos(): void
    {
        $this->assertInstanceOf(City::class, City::factory()->create());
        $this->assertInstanceOf(Agreement::class, Agreement::factory()->create());
        $this->assertInstanceOf(Counselor::class, Counselor::factory()->create());
        $this->assertInstanceOf(Renovation::class, Renovation::factory()->create());
        $this->assertInstanceOf(WhatsappMessage::class, WhatsappMessage::factory()->create());
        $this->assertInstanceOf(Beneficiary::class, Beneficiary::factory()->create());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=ReportFactoriesSmokeTest`
Expected: FAIL — `Class "Database\Factories\CityFactory" not found` (or similar for each).

- [ ] **Step 3: Implement each factory**

`database/factories/CityFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\City>
 */
class CityFactory extends Factory
{
    public function definition(): array
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name'       => fake()->unique()->state(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'department_id' => $departmentId,
            'name'          => fake()->unique()->city(),
        ];
    }
}
```

`database/factories/AgreementFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Agreement>
 */
class AgreementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'     => fake()->unique()->words(2, true),
            'amount'   => fake()->numberBetween(50000, 300000),
            'state'    => 1,
            'city_id'  => City::factory(),
        ];
    }
}
```

`database/factories/CounselorFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Counselor>
 */
class CounselorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => fake()->firstName(),
            'lastname'       => fake()->lastName(),
            'id_card'        => fake()->unique()->numerify('#########'),
            'address'        => fake()->address(),
            'date_admission' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'type_contra'    => 'Corretaje',
            'email'          => fake()->unique()->safeEmail(),
            'password'       => bcrypt('password'),
            'phone'          => fake()->numerify('60#######'),
            'movil'          => fake()->numerify('300#######'),
            'state'          => 1,
            'city_id'        => City::factory(),
            'user_id'        => User::factory(),
        ];
    }
}
```

`database/factories/RenovationFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Affiliate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Renovation>
 */
class RenovationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'date_ini'     => Carbon::now()->toDateString(),
            'date_end'     => Carbon::now()->addYear()->toDateString(),
            'date_payment' => Carbon::now()->toDateString(),
            'value'        => fake()->numberBetween(50000, 300000),
        ];
    }
}
```

`database/factories/WhatsappMessageFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'response'     => json_encode(['messages' => [['id' => 'wamid.' . fake()->uuid()]]]),
            'recipient_id' => '57' . fake()->numerify('3#########'),
            'deleted'      => 0,
            'type'         => 'carnet',
        ];
    }

    // A send Meta rejected — no messages[0].id in the response.
    public function failed(): static
    {
        return $this->state(fn () => [
            'response' => json_encode(['error' => ['message' => 'Template not found']]),
        ]);
    }
}
```

`database/factories/BeneficiaryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Affiliate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'affiliate_id' => Affiliate::factory(),
            'name'         => fake()->firstName(),
            'id_card'      => fake()->unique()->numerify('#########'),
            'bithdate'     => fake()->dateTimeBetween('-30 years', '-1 years')->format('Y-m-d'),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=ReportFactoriesSmokeTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/factories/CityFactory.php database/factories/AgreementFactory.php \
        database/factories/CounselorFactory.php database/factories/RenovationFactory.php \
        database/factories/WhatsappMessageFactory.php database/factories/BeneficiaryFactory.php \
        tests/Feature/Reports/ReportFactoriesSmokeTest.php
git commit -m "test(reports): add missing factories for Counselor, Agreement, Renovation, WhatsappMessage, City, Beneficiary"
```

---

### Task 5: Reports catalog — scoped counselor autocomplete

**Files:**
- Create: `app/Http/Controllers/ReportController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Reports/CounselorsCatalogTest.php`

**Interfaces:**
- Consumes: `Counselor` model, `User::isSuperAdmin()`, `User::isFranchise()`.
- Produces: `GET /api/reports/catalogs/counselors?search=` — `{message, data: [{id, name, lastname}]}`,
  scoped to the caller's franchise, active only, min 2 chars to search, max 20 results. Consumed by
  the frontend's Report 1 counselor autocomplete (separate frontend plan).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Counselor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounselorsCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_ve_asesores_de_cualquier_franquicia(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Counselor::factory()->create(['name' => 'Ana', 'lastname' => 'Gómez', 'state' => 1, 'user_id' => $franchiseA->id]);
        Counselor::factory()->create(['name' => 'Beto', 'lastname' => 'Ruiz', 'state' => 1, 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($admin)->getJson('/api/reports/catalogs/counselors');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_franquicia_solo_ve_sus_propios_asesores(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Counselor::factory()->create(['name' => 'Ana', 'lastname' => 'Gómez', 'state' => 1, 'user_id' => $franchiseA->id]);
        Counselor::factory()->create(['name' => 'Beto', 'lastname' => 'Ruiz', 'state' => 1, 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)->getJson('/api/reports/catalogs/counselors');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'ANA');
    }

    public function test_busqueda_requiere_minimo_dos_caracteres(): void
    {
        $franchise = User::factory()->create(['type' => 2]);
        Counselor::factory()->create(['name' => 'Ana', 'state' => 1, 'user_id' => $franchise->id]);

        $response = $this->actingAs($franchise)->getJson('/api/reports/catalogs/counselors?search=a');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $other = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($other)->getJson('/api/reports/catalogs/counselors');

        $response->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=CounselorsCatalogTest`
Expected: FAIL — route `/api/reports/catalogs/counselors` not found (404).

- [ ] **Step 3: Implement**

`app/Http/Controllers/ReportController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Counselor;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Scoped, active-only counselor catalog for the report filters
     * (select in report 2, autocomplete in report 1).
     */
    public function counselorsCatalog(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isFranchise()) {
            return response()->json([
                'message' => 'No tiene permisos para ver este catálogo',
                'data' => [],
            ], 403);
        }

        $search = trim((string) $request->query('search', ''));

        $query = Counselor::query()->where('state', 1);

        if (!$user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        if (mb_strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        $counselors = $query->orderBy('name')->orderBy('lastname')
            ->limit(20)
            ->get(['id', 'name', 'lastname']);

        return response()->json([
            'message' => 'Asesores obtenidos correctamente',
            'data' => $counselors,
        ], 200);
    }
}
```

In `routes/api.php`, add the import and the route group at the end of the `auth:sanctum` group
(after `content-specialists`):

```php
use App\Http\Controllers\ReportController;
```

```php
    // Reportes
    Route::prefix('reports')->group(function () {
        Route::get('catalogs/counselors', [ReportController::class, 'counselorsCatalog']);
    });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=CounselorsCatalogTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ReportController.php routes/api.php \
        tests/Feature/Reports/CounselorsCatalogTest.php
git commit -m "feat(reports): add scoped counselor catalog endpoint for report filters"
```

---

### Task 6: Report 1 — Ventas (`GET /api/reports/sales`, `/export`)

**Files:**
- Create: `app/Reports/Concerns/AppliesFranchiseScope.php`
- Create: `app/Http/Controllers/Concerns/AuthorizesReportAccess.php`
- Create: `app/Reports/SalesReport.php`
- Create: `app/Reports/Exports/SalesReportExport.php`
- Create: `app/Http/Requests/Reports/SalesReportRequest.php`
- Create: `app/Http/Controllers/ReportExportController.php`
- Modify: `app/Http/Controllers/ReportController.php` (+ `sales()`, + `paginateOrAll()` helper, + use `AuthorizesReportAccess`)
- Modify: `routes/api.php`
- Create: `public/images/reports/logo.png` (copy from `frontend-cm/public/images/logo/logo.png`)
- Test: `tests/Feature/Reports/SalesReportTest.php`

**Interfaces:**
- Consumes: `Affiliate::latestRenovation()` (Task 3), `AppliesFranchiseScope::scopeFranchise()`
  (this task), `Counselor`, `User::isSuperAdmin()`/`isFranchise()`.
- Produces:
  - `AppliesFranchiseScope::scopeFranchise(Builder $query, string $column, User $authUser): Builder`
    — reused by Tasks 7, 9, 10.
  - `AuthorizesReportAccess::reportAccessDenied(bool $franchiseAllowed = true, string $message = 'No tiene permisos para ver este reporte'): ?\Illuminate\Http\JsonResponse`
    — the ONE place the "super admin always, franchise only if allowed, everyone else 403" rule
    lives. Reused by every method in both `ReportController` and `ReportExportController` from
    Task 7 onward — no controller method re-implements this check.
  - `SalesReport::query(array $filters, User $authUser): Builder` and
    `SalesReport::totals(array $filters, User $authUser): array`.
  - `ReportController::paginateOrAll(Builder $query, ?string $perPage, int $default = 25): array{items: \Illuminate\Support\Collection, meta: array}`
    — reused by Tasks 7, 9, 10.

- [ ] **Step 1: Copy the logo asset**

Run (from the `api-cm` repo root):
```bash
mkdir -p public/images/reports
cp ../frontend-cm/public/images/logo/logo.png public/images/reports/logo.png
```

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Counselor;
use App\Models\Renovation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_afiliado_nuevo_sin_renovaciones_se_clasifica_como_nuevo(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create([
            'payment_date' => Carbon::today()->toDateString(),
            'value'        => 150000,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.tipo_venta', 'Nuevo')
                 ->assertJsonPath('data.0.valor_venta', 150000);
    }

    public function test_afiliado_con_varias_renovaciones_toma_solo_la_ultima(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create([
            'payment_date' => Carbon::today()->toDateString(),
            'value'        => 100000,
        ]);

        Renovation::factory()->create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2024-01-01',
            'value'        => 90000,
        ]);
        Renovation::factory()->create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2026-01-01',
            'value'        => 130000,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.tipo_venta', 'Renovación')
                 ->assertJsonPath('data.0.fecha_desde', '2026-01-01')
                 ->assertJsonPath('data.0.valor_venta', 130000);
    }

    public function test_totales_se_calculan_sobre_todo_el_filtro_no_la_pagina(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        Affiliate::factory()->count(3)->create(['payment_date' => Carbon::today()->toDateString(), 'value' => 100000]);
        $renewed = Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'value' => 100000]);
        Renovation::factory()->create(['affiliate_id' => $renewed->id, 'value' => 200000]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales?per_page=1');

        $response->assertStatus(200)
                 ->assertJsonPath('totals.new_count', 3)
                 ->assertJsonPath('totals.new_value', 300000)
                 ->assertJsonPath('totals.renewal_count', 1)
                 ->assertJsonPath('totals.renewal_value', 200000)
                 ->assertJsonPath('meta.total', 4)
                 ->assertJsonCount(1, 'data');
    }

    public function test_franquicia_no_ve_ventas_de_otra_franquicia_aunque_envie_su_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/sales?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_super_admin_ve_ventas_de_todas_las_franquicias(): void
    {
        $admin      = User::factory()->create(['type' => 1]);
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString(), 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $other = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($other)->getJson('/api/reports/sales');

        $response->assertStatus(403);
    }

    public function test_per_page_all_sin_resultados_no_rompe_la_paginacion(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/reports/sales?per_page=all');

        $response->assertStatus(200)
                 ->assertJsonPath('meta.total', 0)
                 ->assertJsonPath('meta.last_page', 1);
    }

    public function test_export_descarga_excel_con_el_mismo_filtro(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['payment_date' => Carbon::today()->toDateString()]);

        $response = $this->actingAs($admin)->get('/api/reports/sales/export');

        $response->assertStatus(200);
        Excel::assertDownloaded(
            'Reporte_Ventas_' . Carbon::today()->format('d-m-Y') . '.xlsx'
        );
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=SalesReportTest`
Expected: FAIL — route `/api/reports/sales` not found (404).

- [ ] **Step 4: Implement the franchise scope trait**

`app/Reports/Concerns/AppliesFranchiseScope.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait AppliesFranchiseScope
{
    /**
     * Restricts $query to the authenticated user's own franchise unless
     * they are super admin. Any franchise id the client sends is applied
     * separately by the caller and only honored for super admin — see
     * each *Report::query() method.
     */
    protected function scopeFranchise(Builder $query, string $column, User $authUser): Builder
    {
        if (!$authUser->isSuperAdmin()) {
            $query->where($column, $authUser->id);
        }

        return $query;
    }
}
```

`app/Http/Controllers/Concerns/AuthorizesReportAccess.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait AuthorizesReportAccess
{
    /**
     * The single access rule for all 6 reports: super admin always gets
     * in; franchise gets in only when $franchiseAllowed (false for the
     * Carnets No Enviados report); anyone else — including type=3, which
     * has no real login flow today but is a valid `users.type` value —
     * is denied. Both ReportController and ReportExportController use
     * this instead of re-checking the role inline in every method, so a
     * fix here fixes all 12 endpoints at once.
     */
    private function reportAccessDenied(
        bool $franchiseAllowed = true,
        string $message = 'No tiene permisos para ver este reporte'
    ): ?JsonResponse {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return null;
        }
        if ($franchiseAllowed && $user->isFranchise()) {
            return null;
        }

        return response()->json(['message' => $message], 403);
    }
}
```

- [ ] **Step 5: Implement the report query class**

`app/Reports/SalesReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class SalesReport
{
    use AppliesFranchiseScope;

    public function query(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()
            ->with(['counselor:id,name,lastname', 'user:id,name', 'latestRenovation'])
            ->whereNotNull('payment_date');

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from'])) {
            $query->where('payment_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('payment_date', '<=', $filters['to']);
        }
        if (!empty($filters['counselor_id'])) {
            $query->where('counselor_id', $filters['counselor_id']);
        }

        return $query->orderByDesc('payment_date')->orderBy('name');
    }

    /** Aggregated over the WHOLE filtered set, never just the current page. */
    public function totals(array $filters, User $authUser): array
    {
        $affiliates = $this->query($filters, $authUser)->get();

        $new      = $affiliates->filter(fn (Affiliate $a) => $a->latestRenovation === null);
        $renewed  = $affiliates->filter(fn (Affiliate $a) => $a->latestRenovation !== null);

        return [
            'new_count'      => $new->count(),
            'new_value'      => (int) $new->sum('value'),
            'renewal_count'  => $renewed->count(),
            'renewal_value'  => (int) $renewed->sum(fn (Affiliate $a) => $a->latestRenovation->value),
        ];
    }
}
```

- [ ] **Step 6: Implement the FormRequest**

`app/Http/Requests/Reports/SalesReportRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SalesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'         => 'nullable|date_format:Y-m-d',
            'to'           => 'nullable|date_format:Y-m-d',
            'franchise_id' => 'nullable|integer|exists:users,id',
            'counselor_id' => 'nullable|integer|exists:counselors,id',
            'per_page'     => 'nullable|in:25,50,100,all',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!empty($this->from) && !empty($this->to) && $this->to < $this->from) {
                $validator->errors()->add('to', 'La fecha "hasta" debe ser mayor o igual a "desde".');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
```

- [ ] **Step 7: Implement the export class**

`app/Reports/Exports/SalesReportExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\SalesReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection()
    {
        return (new SalesReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Fecha Venta', 'Desde', 'Hasta', 'Afiliación', 'Asesor', 'Nombre Afiliado', 'Franquicia', 'Tipo Venta', 'Valor Venta'];
    }

    public function map($affiliate): array
    {
        /** @var Affiliate $affiliate */
        $renovation = $affiliate->latestRenovation;
        $isRenewal  = $renovation !== null;

        return [
            $affiliate->payment_date,
            $isRenewal ? $renovation->date_ini : $affiliate->validity,
            $affiliate->validity_end,
            $affiliate->validity,
            $affiliate->counselor ? trim("{$affiliate->counselor->name} {$affiliate->counselor->lastname}") : '',
            trim("{$affiliate->name} {$affiliate->lastname}"),
            $affiliate->user->name ?? '',
            $isRenewal ? 'Renovación' : 'Nuevo',
            number_format((float) ($isRenewal ? $renovation->value : $affiliate->value), 0, ',', '.'),
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo Contacto Médico');
        $drawing->setPath(public_path('images/reports/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }
}
```

- [ ] **Step 8: Implement the controllers and helper**

In `app/Http/Controllers/ReportController.php`, add imports and the `sales()` method plus the
shared `paginateOrAll()` helper (used by Tasks 7, 9, 10 too). Add `use AuthorizesReportAccess;`
inside the class body (alongside the class declaration):

```php
use App\Models\Affiliate;
use App\Http\Controllers\Concerns\AuthorizesReportAccess;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Reports\SalesReport;
use Illuminate\Database\Eloquent\Builder;
```

Right after the class's opening `{` (before `counselorsCatalog()`, which stays untouched from
Task 5), add:

```php
    use AuthorizesReportAccess;
```

Then add the following two methods anywhere inside the class body (after `counselorsCatalog()` is
fine):

```php
    /**
     * Applies pagination, or returns every row when $perPage === 'all',
     * in the {message,data,meta} shape used across the project.
     *
     * @return array{items: \Illuminate\Support\Collection, meta: array}
     */
    private function paginateOrAll(Builder $query, ?string $perPage, int $default = 25): array
    {
        if ($perPage === 'all') {
            $all = $query->get();

            return [
                'items' => $all,
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $all->count(),
                    'total'        => $all->count(),
                ],
            ];
        }

        $paginated = $query->paginate((int) ($perPage ?? $default));

        return [
            'items' => collect($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ];
    }

    public function sales(SalesReportRequest $request, SalesReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null);

        $items = $result['items']->map(fn (Affiliate $affiliate) => $this->mapSaleRow($affiliate));

        return response()->json([
            'message' => 'Reporte de ventas obtenido correctamente',
            'data'    => $items,
            'meta'    => $result['meta'],
            'totals'  => $report->totals($filters, $user),
        ], 200);
    }

    private function mapSaleRow(Affiliate $affiliate): array
    {
        $renovation = $affiliate->latestRenovation;
        $isRenewal  = $renovation !== null;

        return [
            'id'           => $affiliate->id,
            'payment_date' => $affiliate->payment_date,
            'fecha_desde'  => $isRenewal ? $renovation->date_ini : $affiliate->validity,
            'validity_end' => $affiliate->validity_end,
            'validity'     => $affiliate->validity,
            'counselor'    => $affiliate->counselor ? trim("{$affiliate->counselor->name} {$affiliate->counselor->lastname}") : null,
            'name'         => trim("{$affiliate->name} {$affiliate->lastname}"),
            'franchise'    => $affiliate->user->name ?? null,
            'tipo_venta'   => $isRenewal ? 'Renovación' : 'Nuevo',
            'valor_venta'  => $isRenewal ? $renovation->value : $affiliate->value,
        ];
    }
```

`app/Http/Controllers/ReportExportController.php` (new file):

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesReportAccess;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Reports\Exports\SalesReportExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    use AuthorizesReportAccess;

    public function sales(SalesReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Ventas_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new SalesReportExport($request->validated(), auth()->user()), $filename);
    }
}
```

In `routes/api.php`, add the import and extend the `reports` group started in Task 5:

```php
use App\Http\Controllers\ReportExportController;
```

```php
    Route::prefix('reports')->group(function () {
        Route::get('catalogs/counselors', [ReportController::class, 'counselorsCatalog']);

        Route::get('sales',        [ReportController::class, 'sales']);
        Route::get('sales/export', [ReportExportController::class, 'sales']);
    });
```

- [ ] **Step 9: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=SalesReportTest`
Expected: PASS (all 8 test methods)

- [ ] **Step 10: Commit**

```bash
git add app/Reports app/Http/Controllers/Concerns/AuthorizesReportAccess.php \
        app/Http/Controllers/ReportController.php app/Http/Controllers/ReportExportController.php \
        app/Http/Requests/Reports/SalesReportRequest.php routes/api.php public/images/reports/logo.png \
        tests/Feature/Reports/SalesReportTest.php
git commit -m "feat(reports): add Ventas report with franchise scoping and Excel export"
```

---

### Task 7: Report 2 — Cartera (`GET /api/reports/balance`, `/export`)

**Files:**
- Create: `app/Reports/BalanceReport.php`
- Create: `app/Reports/Exports/BalanceReportExport.php`
- Create: `app/Http/Requests/Reports/BalanceReportRequest.php`
- Modify: `app/Http/Controllers/ReportController.php` (+ `balance()`)
- Modify: `app/Http/Controllers/ReportExportController.php` (+ `balance()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Reports/BalanceReportTest.php`

**Interfaces:**
- Consumes: `AppliesFranchiseScope` (Task 6), `ReportController::paginateOrAll()` (Task 6).
- Produces: `BalanceReport::query()`, `BalanceReport::totalBalance()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Counselor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class BalanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_incluye_afiliados_con_saldo_mayor_a_cero(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['balance' => 50000]);
        Affiliate::factory()->create(['balance' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/reports/balance');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_total_de_saldo_se_calcula_sobre_todo_el_filtro(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['balance' => 30000]);
        Affiliate::factory()->create(['balance' => 20000]);

        $response = $this->actingAs($admin)->getJson('/api/reports/balance?per_page=1');

        $response->assertStatus(200)
                 ->assertJsonPath('total_balance', 50000)
                 ->assertJsonPath('meta.total', 2)
                 ->assertJsonCount(1, 'data');
    }

    public function test_franquicia_no_ve_cartera_de_otra_franquicia_aunque_envie_su_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['balance' => 10000, 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['balance' => 10000, 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/balance?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['balance' => 10000]);

        $response = $this->actingAs($admin)->get('/api/reports/balance/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Cartera_' . now()->format('d-m-Y') . '.xlsx');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=BalanceReportTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Implement the report query class**

`app/Reports/BalanceReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class BalanceReport
{
    use AppliesFranchiseScope;

    public function query(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()
            ->with(['counselor:id,name,lastname'])
            ->where('stade', 1)
            ->where('balance', '>', 0)
            ->whereHas('counselor', fn (Builder $q) => $q->where('state', 1));

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['counselor_id'])) {
            $query->where('counselor_id', $filters['counselor_id']);
        }

        return $query->orderBy('name');
    }

    public function totalBalance(array $filters, User $authUser): int
    {
        return (int) $this->query($filters, $authUser)->sum('balance');
    }
}
```

- [ ] **Step 4: Implement the FormRequest**

`app/Http/Requests/Reports/BalanceReportRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BalanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'franchise_id' => 'nullable|integer|exists:users,id',
            'counselor_id' => 'nullable|integer|exists:counselors,id',
            'per_page'     => 'nullable|in:25,50,100,all',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
```

- [ ] **Step 5: Implement the export class**

`app/Reports/Exports/BalanceReportExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\BalanceReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class BalanceReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection()
    {
        return (new BalanceReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Asesor', 'Nombre Afiliado', 'Valor Saldo', 'Fecha de Ingreso'];
    }

    public function map($affiliate): array
    {
        /** @var Affiliate $affiliate */
        return [
            $affiliate->counselor ? trim("{$affiliate->counselor->name} {$affiliate->counselor->lastname}") : '',
            trim("{$affiliate->name} {$affiliate->lastname}"),
            '$ ' . number_format((float) $affiliate->balance, 0, ',', '.'),
            $affiliate->validity,
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo Contacto Médico');
        $drawing->setPath(public_path('images/reports/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }
}
```

- [ ] **Step 6: Wire the controllers and route**

In `app/Http/Controllers/ReportController.php`, add imports and method:

```php
use App\Http\Requests\Reports\BalanceReportRequest;
use App\Reports\BalanceReport;
```

```php
    public function balance(BalanceReportRequest $request, BalanceReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null, 15);

        $items = $result['items']->map(fn (Affiliate $a) => [
            'id'        => $a->id,
            'counselor' => $a->counselor ? trim("{$a->counselor->name} {$a->counselor->lastname}") : null,
            'name'      => trim("{$a->name} {$a->lastname}"),
            'balance'   => $a->balance,
            'validity'  => $a->validity,
        ]);

        return response()->json([
            'message'       => 'Reporte de cartera obtenido correctamente',
            'data'          => $items,
            'meta'          => $result['meta'],
            'total_balance' => $report->totalBalance($filters, $user),
        ], 200);
    }
```

In `app/Http/Controllers/ReportExportController.php`, add imports and method:

```php
use App\Http\Requests\Reports\BalanceReportRequest;
use App\Reports\Exports\BalanceReportExport;
```

```php
    public function balance(BalanceReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Cartera_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new BalanceReportExport($request->validated(), auth()->user()), $filename);
    }
```

In `routes/api.php`, inside the `reports` group:

```php
        Route::get('balance',        [ReportController::class, 'balance']);
        Route::get('balance/export', [ReportExportController::class, 'balance']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=BalanceReportTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Reports/BalanceReport.php app/Reports/Exports/BalanceReportExport.php \
        app/Http/Requests/Reports/BalanceReportRequest.php app/Http/Controllers/ReportController.php \
        app/Http/Controllers/ReportExportController.php routes/api.php \
        tests/Feature/Reports/BalanceReportTest.php
git commit -m "feat(reports): add Cartera report with balance total and Excel export"
```

---

### Task 8: Report 3 — Resumen de Afiliados (`GET /api/reports/affiliates-summary`, `/export`)

**Files:**
- Create: `app/Reports/AffiliatesSummaryReport.php`
- Create: `app/Reports/Exports/AffiliatesSummaryReportExport.php`
- Create: `app/Http/Requests/Reports/AffiliatesSummaryReportRequest.php`
- Modify: `app/Http/Controllers/ReportController.php` (+ `affiliatesSummary()`)
- Modify: `app/Http/Controllers/ReportExportController.php` (+ `affiliatesSummary()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Reports/AffiliatesSummaryReportTest.php`

**Interfaces:**
- Consumes: `AppliesFranchiseScope` (Task 6), `Beneficiary` model.
- Produces: `AffiliatesSummaryReport::indicators(array $filters, User $authUser): array` — keys
  `titulares`, `titulares_activos`, `titulares_inactivos`, `beneficiarios`,
  `beneficiarios_activos`, `beneficiarios_inactivos`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AffiliatesSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_indicadores_basicos(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        $active   = Affiliate::factory()->create(['validity_end' => Carbon::tomorrow()->toDateString()]);
        $inactive = Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString()]);
        Beneficiary::factory()->count(2)->create(['affiliate_id' => $active->id]);
        Beneficiary::factory()->count(1)->create(['affiliate_id' => $inactive->id]);

        $response = $this->actingAs($admin)->getJson('/api/reports/affiliates-summary');

        $response->assertStatus(200)
                 ->assertJsonPath('data.titulares', 2)
                 ->assertJsonPath('data.titulares_activos', 1)
                 ->assertJsonPath('data.titulares_inactivos', 1)
                 ->assertJsonPath('data.beneficiarios', 3)
                 ->assertJsonPath('data.beneficiarios_activos', 2)
                 ->assertJsonPath('data.beneficiarios_inactivos', 1);
    }

    public function test_beneficiarios_de_franquicia_a_no_se_cuentan_en_b(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        $affiliateA = Affiliate::factory()->create(['user_id' => $franchiseA->id]);
        $affiliateB = Affiliate::factory()->create(['user_id' => $franchiseB->id]);
        Beneficiary::factory()->count(2)->create(['affiliate_id' => $affiliateA->id]);
        Beneficiary::factory()->count(5)->create(['affiliate_id' => $affiliateB->id]);

        $response = $this->actingAs($franchiseA)->getJson('/api/reports/affiliates-summary');

        $response->assertStatus(200)
                 ->assertJsonPath('data.titulares', 1)
                 ->assertJsonPath('data.beneficiarios', 2);
    }

    public function test_franquicia_no_ve_indicadores_de_otra_franquicia_aunque_envie_su_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/affiliates-summary?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonPath('data.titulares', 1);
    }

    public function test_filtro_de_fechas_solo_se_aplica_si_vienen_ambas(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['validity' => '2020-01-01']);
        Affiliate::factory()->create(['validity' => '2026-01-01']);

        $response = $this->actingAs($admin)
            ->getJson('/api/reports/affiliates-summary?from=2025-01-01');

        $response->assertStatus(200)->assertJsonPath('data.titulares', 2);

        $response = $this->actingAs($admin)
            ->getJson('/api/reports/affiliates-summary?from=2025-01-01&to=2026-12-31');

        $response->assertStatus(200)->assertJsonPath('data.titulares', 1);
    }

    public function test_export_descarga_excel_con_los_6_indicadores(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create();

        $response = $this->actingAs($admin)->get('/api/reports/affiliates-summary/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Resumen_Afiliados_' . now()->format('d-m-Y') . '.xlsx');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=AffiliatesSummaryReportTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Implement the report class**

`app/Reports/AffiliatesSummaryReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class AffiliatesSummaryReport
{
    use AppliesFranchiseScope;

    private function baseQuery(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()->where('stade', 1);

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from']) && !empty($filters['to'])) {
            $query->whereBetween('validity', [$filters['from'], $filters['to']]);
        }
        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        } elseif (!empty($filters['department_id'])) {
            $query->whereHas('city', fn (Builder $q) => $q->where('department_id', $filters['department_id']));
        }

        return $query;
    }

    public function indicators(array $filters, User $authUser): array
    {
        $today = now()->toDateString();

        $titularIds = (clone $this->baseQuery($filters, $authUser))->pluck('id');

        $titulares          = $titularIds->count();
        $titularesActivos   = (clone $this->baseQuery($filters, $authUser))
            ->where('validity_end', '>=', $today)->count();
        $titularesInactivos = $titulares - $titularesActivos;

        $activeTitularIds = (clone $this->baseQuery($filters, $authUser))
            ->where('validity_end', '>=', $today)->pluck('id');

        $beneficiarios         = Beneficiary::whereIn('affiliate_id', $titularIds)->count();
        $beneficiariosActivos  = Beneficiary::whereIn('affiliate_id', $activeTitularIds)->count();
        $beneficiariosInactivos = $beneficiarios - $beneficiariosActivos;

        return [
            'titulares'               => $titulares,
            'titulares_activos'       => $titularesActivos,
            'titulares_inactivos'     => $titularesInactivos,
            'beneficiarios'           => $beneficiarios,
            'beneficiarios_activos'   => $beneficiariosActivos,
            'beneficiarios_inactivos' => $beneficiariosInactivos,
        ];
    }
}
```

- [ ] **Step 4: Implement the FormRequest**

`app/Http/Requests/Reports/AffiliatesSummaryReportRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AffiliatesSummaryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'          => 'nullable|date_format:Y-m-d',
            'to'            => 'nullable|date_format:Y-m-d',
            'city_id'       => 'nullable|integer|exists:cities,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'franchise_id'  => 'nullable|integer|exists:users,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!empty($this->from) && !empty($this->to) && $this->to < $this->from) {
                $validator->errors()->add('to', 'La fecha "hasta" debe ser mayor o igual a "desde".');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
```

- [ ] **Step 5: Implement the export class**

`app/Reports/Exports/AffiliatesSummaryReportExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\AffiliatesSummaryReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AffiliatesSummaryReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function array(): array
    {
        $indicators = (new AffiliatesSummaryReport())->indicators($this->filters, $this->authUser);

        return [
            ['Titulares', $indicators['titulares']],
            ['Titulares activos', $indicators['titulares_activos']],
            ['Titulares inactivos', $indicators['titulares_inactivos']],
            ['Beneficiarios', $indicators['beneficiarios']],
            ['Beneficiarios activos', $indicators['beneficiarios_activos']],
            ['Beneficiarios inactivos', $indicators['beneficiarios_inactivos']],
        ];
    }

    public function headings(): array
    {
        return ['Indicador', 'Cantidad'];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo Contacto Médico');
        $drawing->setPath(public_path('images/reports/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }
}
```

- [ ] **Step 6: Wire the controllers and route**

In `app/Http/Controllers/ReportController.php`:

```php
use App\Http\Requests\Reports\AffiliatesSummaryReportRequest;
use App\Reports\AffiliatesSummaryReport;
```

```php
    public function affiliatesSummary(AffiliatesSummaryReportRequest $request, AffiliatesSummaryReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $filters = $request->validated();

        return response()->json([
            'message' => 'Indicadores de afiliados obtenidos correctamente',
            'data'    => $report->indicators($filters, auth()->user()),
            'from'    => $filters['from'] ?? null,
            'to'      => $filters['to'] ?? null,
        ], 200);
    }
```

In `app/Http/Controllers/ReportExportController.php`:

```php
use App\Http\Requests\Reports\AffiliatesSummaryReportRequest;
use App\Reports\Exports\AffiliatesSummaryReportExport;
```

```php
    public function affiliatesSummary(AffiliatesSummaryReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Resumen_Afiliados_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new AffiliatesSummaryReportExport($request->validated(), auth()->user()), $filename);
    }
```

In `routes/api.php`, inside the `reports` group:

```php
        Route::get('affiliates-summary',        [ReportController::class, 'affiliatesSummary']);
        Route::get('affiliates-summary/export', [ReportExportController::class, 'affiliatesSummary']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=AffiliatesSummaryReportTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Reports/AffiliatesSummaryReport.php app/Reports/Exports/AffiliatesSummaryReportExport.php \
        app/Http/Requests/Reports/AffiliatesSummaryReportRequest.php app/Http/Controllers/ReportController.php \
        app/Http/Controllers/ReportExportController.php routes/api.php \
        tests/Feature/Reports/AffiliatesSummaryReportTest.php
git commit -m "feat(reports): add Resumen de Afiliados indicators report with Excel export"
```

---

### Task 9: Report 4 — Citas (`GET /api/reports/appointments`, `/export`)

**Files:**
- Create: `app/Reports/AppointmentsReport.php`
- Create: `app/Reports/Exports/AppointmentsReportExport.php`
- Create: `app/Http/Requests/Reports/AppointmentsReportRequest.php`
- Modify: `app/Http/Controllers/ReportController.php` (+ `appointments()`)
- Modify: `app/Http/Controllers/ReportExportController.php` (+ `appointments()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Reports/AppointmentsReportTest.php`

**Interfaces:**
- Consumes: `AppliesFranchiseScope`, `ReportController::paginateOrAll()` (both Task 6).
- Produces: `AppointmentsReport::query()`, resolving `patient_name` via a single query (no N+1).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\Beneficiary;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AppointmentsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_resuelve_nombre_de_titular_y_beneficiario_sin_n_mas_1(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $doctor    = Doctor::factory()->create(['name' => 'Carlos', 'lastname' => 'Pérez']);
        $affiliate = Affiliate::factory()->create(['name' => 'Ana', 'lastname' => 'Gómez']);
        $beneficiary = Beneficiary::factory()->create(['affiliate_id' => $affiliate->id, 'name' => 'Luis']);

        Appointment::factory()->create([
            'afi_code' => $affiliate->id, 'type' => 1, 'doctor_id' => $doctor->id, 'date' => Carbon::today()->toDateString(),
        ]);
        Appointment::factory()->create([
            'afi_code' => $beneficiary->id, 'type' => 2, 'doctor_id' => $doctor->id, 'date' => Carbon::today()->toDateString(),
        ]);

        DB::enableQueryLog();
        $response = $this->actingAs($admin)->getJson('/api/reports/appointments');
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertStatus(200)->assertJsonCount(2, 'data');
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('ANA GÓMEZ (Titular)'));
        $this->assertTrue($names->contains('LUIS (Beneficiario)'));
        // 1 auth lookup + 1 count + 1 select + 1 eager-load doctor.city — a fixed, small number
        // regardless of row count proves there is no per-row query.
        $this->assertLessThanOrEqual(6, $queryCount);
    }

    public function test_franquicia_se_acota_por_appointments_user_id(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);
        $doctor     = Doctor::factory()->create();

        Appointment::factory()->create(['user_id' => $franchiseA->id, 'doctor_id' => $doctor->id]);
        Appointment::factory()->create(['user_id' => $franchiseB->id, 'doctor_id' => $doctor->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/appointments?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin  = User::factory()->create(['type' => 1]);
        $doctor = Doctor::factory()->create();
        Appointment::factory()->create(['doctor_id' => $doctor->id]);

        $response = $this->actingAs($admin)->get('/api/reports/appointments/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Citas_' . now()->format('d-m-Y') . '.xlsx');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=AppointmentsReportTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Implement the report class**

`app/Reports/AppointmentsReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Appointment;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsReport
{
    use AppliesFranchiseScope;

    public function query(array $filters, User $authUser): Builder
    {
        $query = Appointment::query()
            ->select('appointments.*')
            ->selectRaw('COALESCE(aff.name, ben.name) as patient_name')
            ->with(['doctor:id,name,lastname,city_id', 'doctor.city:id,name'])
            ->leftJoin('affiliates as aff', function ($join) {
                $join->on('aff.id', '=', 'appointments.afi_code')->where('appointments.type', 1);
            })
            ->leftJoin('beneficiaries as ben', function ($join) {
                $join->on('ben.id', '=', 'appointments.afi_code')->where('appointments.type', 2);
            });

        $this->scopeFranchise($query, 'appointments.user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('appointments.user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from'])) {
            $query->where('appointments.date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('appointments.date', '<=', $filters['to']);
        }
        if (!empty($filters['doctor_id'])) {
            $query->where('appointments.doctor_id', $filters['doctor_id']);
        }

        return $query->orderByDesc('appointments.date');
    }
}
```

- [ ] **Step 4: Implement the FormRequest**

`app/Http/Requests/Reports/AppointmentsReportRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AppointmentsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'         => 'nullable|date_format:Y-m-d',
            'to'           => 'nullable|date_format:Y-m-d',
            'doctor_id'    => 'nullable|integer|exists:doctors,id',
            'franchise_id' => 'nullable|integer|exists:users,id',
            'per_page'     => 'nullable|in:25,50,100,all',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!empty($this->from) && !empty($this->to) && $this->to < $this->from) {
                $validator->errors()->add('to', 'La fecha "hasta" debe ser mayor o igual a "desde".');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
```

- [ ] **Step 5: Implement the export class**

`app/Reports/Exports/AppointmentsReportExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\AppointmentsReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AppointmentsReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection()
    {
        return (new AppointmentsReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Nombre', 'Médico', 'Ciudad', 'Fecha'];
    }

    public function map($appointment): array
    {
        $suffix = $appointment->type === 1 ? ' (Titular)' : ' (Beneficiario)';

        return [
            $appointment->patient_name . $suffix,
            $appointment->doctor ? trim("{$appointment->doctor->name} {$appointment->doctor->lastname}") : '',
            $appointment->doctor?->city?->name ?? '',
            $appointment->date,
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo Contacto Médico');
        $drawing->setPath(public_path('images/reports/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }
}
```

- [ ] **Step 6: Wire the controllers and route**

In `app/Http/Controllers/ReportController.php`:

```php
use App\Http\Requests\Reports\AppointmentsReportRequest;
use App\Reports\AppointmentsReport;
```

```php
    public function appointments(AppointmentsReportRequest $request, AppointmentsReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null);

        $items = $result['items']->map(function ($appointment) {
            $suffix = $appointment->type === 1 ? ' (Titular)' : ' (Beneficiario)';

            return [
                'id'     => $appointment->id,
                'name'   => $appointment->patient_name . $suffix,
                'doctor' => $appointment->doctor ? trim("{$appointment->doctor->name} {$appointment->doctor->lastname}") : null,
                'city'   => $appointment->doctor?->city?->name,
                'date'   => $appointment->date,
            ];
        });

        return response()->json([
            'message' => 'Reporte de citas obtenido correctamente',
            'data'    => $items,
            'meta'    => $result['meta'],
        ], 200);
    }
```

In `app/Http/Controllers/ReportExportController.php`:

```php
use App\Http\Requests\Reports\AppointmentsReportRequest;
use App\Reports\Exports\AppointmentsReportExport;
```

```php
    public function appointments(AppointmentsReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Citas_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new AppointmentsReportExport($request->validated(), auth()->user()), $filename);
    }
```

In `routes/api.php`, inside the `reports` group:

```php
        Route::get('appointments',        [ReportController::class, 'appointments']);
        Route::get('appointments/export', [ReportExportController::class, 'appointments']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=AppointmentsReportTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Reports/AppointmentsReport.php app/Reports/Exports/AppointmentsReportExport.php \
        app/Http/Requests/Reports/AppointmentsReportRequest.php app/Http/Controllers/ReportController.php \
        app/Http/Controllers/ReportExportController.php routes/api.php \
        tests/Feature/Reports/AppointmentsReportTest.php
git commit -m "feat(reports): add Citas report resolving titular/beneficiario name without N+1"
```

---

### Task 10: Report 5 — Sin Renovación (`GET /api/reports/non-renewed-affiliates`, `/export`)

**Files:**
- Create: `app/Reports/NonRenewedAffiliatesReport.php`
- Create: `app/Reports/Exports/NonRenewedAffiliatesReportExport.php`
- Create: `app/Http/Requests/Reports/NonRenewedAffiliatesReportRequest.php`
- Modify: `app/Http/Controllers/ReportController.php` (+ `nonRenewedAffiliates()`)
- Modify: `app/Http/Controllers/ReportExportController.php` (+ `nonRenewedAffiliates()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Reports/NonRenewedAffiliatesReportTest.php`

**Interfaces:**
- Consumes: `AppliesFranchiseScope`, `ReportController::paginateOrAll()` (both Task 6).
- Produces: `NonRenewedAffiliatesReport::query()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class NonRenewedAffiliatesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_incluye_afiliados_ya_inactivados_por_el_cron(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        // stade = 2: ya lo pasó el cron affiliates:update-expired por estar vencido.
        Affiliate::factory()->create(['stade' => 2, 'validity_end' => Carbon::yesterday()->toDateString()]);
        // stade = 1, vence hoy: aún no le corrió el cron.
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => Carbon::today()->toDateString()]);
        // Vigente: no debe aparecer.
        Affiliate::factory()->create(['stade' => 1, 'validity_end' => Carbon::tomorrow()->toDateString()]);

        $response = $this->actingAs($admin)->getJson('/api/reports/non-renewed-affiliates');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_franquicia_no_ve_los_de_otra_franquicia(): void
    {
        $franchiseA = User::factory()->create(['type' => 2]);
        $franchiseB = User::factory()->create(['type' => 2]);

        Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString(), 'user_id' => $franchiseA->id]);
        Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString(), 'user_id' => $franchiseB->id]);

        $response = $this->actingAs($franchiseA)
            ->getJson('/api/reports/non-renewed-affiliates?franchise_id=' . $franchiseB->id);

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['validity_end' => Carbon::yesterday()->toDateString()]);

        $response = $this->actingAs($admin)->get('/api/reports/non-renewed-affiliates/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Sin_Renovacion_' . now()->format('d-m-Y') . '.xlsx');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=NonRenewedAffiliatesReportTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Implement the report class**

`app/Reports/NonRenewedAffiliatesReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class NonRenewedAffiliatesReport
{
    use AppliesFranchiseScope;

    /**
     * No `stade` filter on purpose: the daily affiliates:update-expired cron
     * already flips expired affiliates to stade=2, so filtering by stade=1
     * (as the legacy report did) would leave this report almost empty —
     * decided with the user, see spec Contexto.
     */
    public function query(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()
            ->with(['user:id,name'])
            ->where('validity_end', '<=', now()->toDateString());

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from'])) {
            $query->where('validity_end', '>=', $filters['from']);
        }

        return $query->orderByDesc('validity_end');
    }
}
```

- [ ] **Step 4: Implement the FormRequest**

`app/Http/Requests/Reports/NonRenewedAffiliatesReportRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class NonRenewedAffiliatesReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'         => 'nullable|date_format:Y-m-d',
            'franchise_id' => 'nullable|integer|exists:users,id',
            'per_page'     => 'nullable|in:25,50,100,all',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
```

- [ ] **Step 5: Implement the export class**

`app/Reports/Exports/NonRenewedAffiliatesReportExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\NonRenewedAffiliatesReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class NonRenewedAffiliatesReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection()
    {
        return (new NonRenewedAffiliatesReport())->query($this->filters, $this->authUser)->get();
    }

    public function headings(): array
    {
        return ['Hasta', 'Titular', 'Teléfono', 'Celular', 'Franquicia'];
    }

    public function map($affiliate): array
    {
        return [
            $affiliate->validity_end,
            trim("{$affiliate->name} {$affiliate->lastname}"),
            $affiliate->phone,
            $affiliate->movil,
            $affiliate->user->name ?? '',
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo Contacto Médico');
        $drawing->setPath(public_path('images/reports/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }
}
```

- [ ] **Step 6: Wire the controllers and route**

In `app/Http/Controllers/ReportController.php`:

```php
use App\Http\Requests\Reports\NonRenewedAffiliatesReportRequest;
use App\Reports\NonRenewedAffiliatesReport;
```

```php
    public function nonRenewedAffiliates(NonRenewedAffiliatesReportRequest $request, NonRenewedAffiliatesReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null);

        $items = $result['items']->map(fn (Affiliate $a) => [
            'id'           => $a->id,
            'validity_end' => $a->validity_end,
            'name'         => trim("{$a->name} {$a->lastname}"),
            'phone'        => $a->phone,
            'movil'        => $a->movil,
            'franchise'    => $a->user->name ?? null,
        ]);

        return response()->json([
            'message' => 'Reporte de clientes sin renovación obtenido correctamente',
            'data'    => $items,
            'meta'    => $result['meta'],
        ], 200);
    }
```

In `app/Http/Controllers/ReportExportController.php`:

```php
use App\Http\Requests\Reports\NonRenewedAffiliatesReportRequest;
use App\Reports\Exports\NonRenewedAffiliatesReportExport;
```

```php
    public function nonRenewedAffiliates(NonRenewedAffiliatesReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Sin_Renovacion_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new NonRenewedAffiliatesReportExport($request->validated(), auth()->user()), $filename);
    }
```

In `routes/api.php`, inside the `reports` group:

```php
        Route::get('non-renewed-affiliates',        [ReportController::class, 'nonRenewedAffiliates']);
        Route::get('non-renewed-affiliates/export', [ReportExportController::class, 'nonRenewedAffiliates']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=NonRenewedAffiliatesReportTest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Reports/NonRenewedAffiliatesReport.php app/Reports/Exports/NonRenewedAffiliatesReportExport.php \
        app/Http/Requests/Reports/NonRenewedAffiliatesReportRequest.php app/Http/Controllers/ReportController.php \
        app/Http/Controllers/ReportExportController.php routes/api.php \
        tests/Feature/Reports/NonRenewedAffiliatesReportTest.php
git commit -m "feat(reports): add Sin Renovacion report, ignoring stade per confirmed decision"
```

---

### Task 11: Report 6 — Carnets No Enviados (`GET /api/reports/unsent-carnets`, `/export`)

**Files:**
- Create: `app/Reports/UnsentCarnetsReport.php`
- Create: `app/Reports/Exports/UnsentCarnetsReportExport.php`
- Create: `app/Http/Requests/Reports/UnsentCarnetsReportRequest.php`
- Modify: `app/Http/Controllers/ReportController.php` (+ `unsentCarnets()`)
- Modify: `app/Http/Controllers/ReportExportController.php` (+ `unsentCarnets()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/Reports/UnsentCarnetsReportTest.php`

**Interfaces:**
- Consumes: `WhatsappMessage` model, `Affiliate` model, `User::isSuperAdmin()`.
- Produces: `UnsentCarnetsReport::failed(array $filters, User $authUser): \Illuminate\Support\Collection`
  — only messages whose `response` lacks `messages[0].id`, joined to the matching affiliate by
  stripping the `57` country prefix from `recipient_id`.

Report 6 has no `AppliesFranchiseScope` use — it is super-admin only, so no franchise column
filtering is needed beyond the explicit `franchise_id` request filter.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class UnsentCarnetsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_57_mas_movil_matchea_al_afiliado(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['movil' => '3001234567']);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573001234567',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_envio_exitoso_no_aparece_como_no_enviado(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create(['movil' => '3009876543']);

        WhatsappMessage::factory()->create([
            'recipient_id' => '573009876543',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_prefijo_sin_afiliado_correspondiente_no_rompe_el_reporte(): void
    {
        $admin = User::factory()->create(['type' => 1]);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '999999999999',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_type_cita_no_se_incluye(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['movil' => '3001112233']);

        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573001112233',
            'type'         => 'cita',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_franquicia_recibe_403(): void
    {
        $franchise = User::factory()->create(['type' => 2]);

        $response = $this->actingAs($franchise)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(403);
    }

    public function test_rol_no_autorizado_recibe_403(): void
    {
        $other = User::factory()->create(['type' => 3]);

        $response = $this->actingAs($other)->getJson('/api/reports/unsent-carnets');

        $response->assertStatus(403);
    }

    public function test_export_descarga_excel(): void
    {
        Excel::fake();

        $admin = User::factory()->create(['type' => 1]);
        Affiliate::factory()->create(['movil' => '3005554433']);
        WhatsappMessage::factory()->failed()->create([
            'recipient_id' => '573005554433',
            'type'         => 'carnet',
            'created_at'   => Carbon::today(),
        ]);

        $response = $this->actingAs($admin)->get('/api/reports/unsent-carnets/export');

        $response->assertStatus(200);
        Excel::assertDownloaded('Reporte_Carnets_No_Enviados_' . now()->format('d-m-Y') . '.xlsx');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `XDEBUG_MODE=off php artisan test --filter=UnsentCarnetsReportTest`
Expected: FAIL — route not found.

- [ ] **Step 3: Implement the report class**

`app/Reports/UnsentCarnetsReport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnsentCarnetsReport
{
    /**
     * Candidate carnet sends in the date range, joined to the matching
     * affiliate by stripping the '57' country prefix WhatsAppClient always
     * prepends (see WhatsAppClient::sendTemplate()). Super-admin only —
     * the controller returns 403 before this is ever called for a
     * franchise, so no AppliesFranchiseScope here.
     */
    private function candidates(array $filters, User $authUser): Builder
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to   = $filters['to']   ?? now()->endOfMonth()->toDateString();

        $query = WhatsappMessage::query()
            ->select([
                'whatsapp_messages.*',
                'affiliates.name as affiliate_name',
                'affiliates.lastname as affiliate_lastname',
                'affiliates.phone as affiliate_phone',
                'affiliates.movil as affiliate_movil',
                'franchise.name as franchise_name',
            ])
            ->where('whatsapp_messages.type', 'carnet')
            ->whereRaw('DATE(whatsapp_messages.created_at) BETWEEN ? AND ?', [$from, $to])
            ->join('affiliates', function ($join) {
                $join->on('affiliates.movil', '=', DB::raw('SUBSTR(whatsapp_messages.recipient_id, 3)'));
            })
            ->leftJoin('users as franchise', 'franchise.id', '=', 'affiliates.user_id');

        if (!empty($filters['franchise_id'])) {
            $query->where('affiliates.user_id', $filters['franchise_id']);
        }

        return $query->orderByDesc('whatsapp_messages.created_at');
    }

    /** Messages Meta never confirmed with a messages[0].id. */
    public function failed(array $filters, User $authUser): Collection
    {
        return $this->candidates($filters, $authUser)
            ->get()
            ->reject(function ($message) {
                $decoded = json_decode($message->response, true);

                return !empty($decoded['messages'][0]['id'] ?? null);
            })
            ->values();
    }
}
```

- [ ] **Step 4: Implement the FormRequest**

`app/Http/Requests/Reports/UnsentCarnetsReportRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UnsentCarnetsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'         => 'nullable|date_format:Y-m-d',
            'to'           => 'nullable|date_format:Y-m-d',
            'franchise_id' => 'nullable|integer|exists:users,id',
            'per_page'     => 'nullable|in:25,50,100,all',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!empty($this->from) && !empty($this->to) && $this->to < $this->from) {
                $validator->errors()->add('to', 'La fecha "hasta" debe ser mayor o igual a "desde".');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación',
            'errors'  => $validator->errors(),
        ], 400));
    }
}
```

- [ ] **Step 5: Implement the export class**

`app/Reports/Exports/UnsentCarnetsReportExport.php`:

```php
<?php

declare(strict_types=1);

namespace App\Reports\Exports;

use App\Models\User;
use App\Reports\UnsentCarnetsReport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class UnsentCarnetsReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithDrawings
{
    public function __construct(private array $filters, private User $authUser)
    {
    }

    public function collection()
    {
        return (new UnsentCarnetsReport())->failed($this->filters, $this->authUser);
    }

    public function headings(): array
    {
        return ['Fecha', 'Titular', 'Teléfono', 'Celular', 'Franquicia'];
    }

    public function map($message): array
    {
        return [
            substr((string) $message->created_at, 0, 10),
            trim("{$message->affiliate_name} {$message->affiliate_lastname}"),
            $message->affiliate_phone,
            $message->affiliate_movil,
            $message->franchise_name ?? '',
        ];
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo Contacto Médico');
        $drawing->setPath(public_path('images/reports/logo.png'));
        $drawing->setHeight(50);
        $drawing->setCoordinates('A1');

        return $drawing;
    }
}
```

- [ ] **Step 6: Wire the controllers and route**

In `app/Http/Controllers/ReportController.php`:

```php
use App\Http\Requests\Reports\UnsentCarnetsReportRequest;
use App\Reports\UnsentCarnetsReport;
```

```php
    public function unsentCarnets(UnsentCarnetsReportRequest $request, UnsentCarnetsReport $report)
    {
        if ($denied = $this->reportAccessDenied(franchiseAllowed: false)) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $failed  = $report->failed($filters, $user);

        $rawPerPage = $filters['per_page'] ?? '25';
        $perPage    = $rawPerPage === 'all' ? max($failed->count(), 1) : (int) $rawPerPage;
        $page       = $rawPerPage === 'all' ? 1 : max(1, (int) $request->query('page', 1));

        $items = $failed->forPage($page, $perPage)->map(fn ($m) => [
            'date'      => substr((string) $m->created_at, 0, 10),
            'name'      => trim("{$m->affiliate_name} {$m->affiliate_lastname}"),
            'phone'     => $m->affiliate_phone,
            'movil'     => $m->affiliate_movil,
            'franchise' => $m->franchise_name,
        ])->values();

        return response()->json([
            'message' => 'Reporte de carnets no enviados obtenido correctamente',
            'data'    => $items,
            'meta'    => [
                'current_page' => $page,
                'last_page'    => $rawPerPage === 'all' ? 1 : (int) max(1, ceil($failed->count() / $perPage)),
                'per_page'     => $rawPerPage === 'all' ? $failed->count() : $perPage,
                'total'        => $failed->count(),
            ],
        ], 200);
    }
```

In `app/Http/Controllers/ReportExportController.php`:

```php
use App\Http\Requests\Reports\UnsentCarnetsReportRequest;
use App\Reports\Exports\UnsentCarnetsReportExport;
```

```php
    public function unsentCarnets(UnsentCarnetsReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(franchiseAllowed: false, message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Carnets_No_Enviados_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new UnsentCarnetsReportExport($request->validated(), auth()->user()), $filename);
    }
```

In `routes/api.php`, inside the `reports` group:

```php
        Route::get('unsent-carnets',        [ReportController::class, 'unsentCarnets']);
        Route::get('unsent-carnets/export', [ReportExportController::class, 'unsentCarnets']);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `XDEBUG_MODE=off php artisan test --filter=UnsentCarnetsReportTest`
Expected: PASS (all 7 test methods)

- [ ] **Step 8: Commit**

```bash
git add app/Reports/UnsentCarnetsReport.php app/Reports/Exports/UnsentCarnetsReportExport.php \
        app/Http/Requests/Reports/UnsentCarnetsReportRequest.php app/Http/Controllers/ReportController.php \
        app/Http/Controllers/ReportExportController.php routes/api.php \
        tests/Feature/Reports/UnsentCarnetsReportTest.php
git commit -m "feat(reports): add Carnets No Enviados report, super-admin only"
```

---

### Task 12: Document the module in `CLAUDE.md` and run the full suite

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Run the full test suite to confirm nothing regressed**

Run: `XDEBUG_MODE=off php artisan test --process-isolation`
Expected: all tests pass, including every `tests/Feature/Reports/*` file from Tasks 2–11.

- [ ] **Step 2: Add a "Módulo de Reportes" section to `CLAUDE.md`**

Insert a new `##` section before `## Testing`, summarizing (for future agents, not duplicating the
full spec): the 6 report endpoints and their `/export` counterparts under `GET /api/reports/*`;
that `App\Reports\*Report::query()` is the single source of truth shared by listing, totals, and
Excel; the `AppliesFranchiseScope` trait and which 5 reports use it (not report 6); that report 5
ignores `stade` on purpose; that report 6 determines "no enviado" by parsing `response`, never
`deleted`; and a pointer to `docs/superpowers/specs/2026-09-22-reports-module-design.md` for the
full rationale.

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md
git commit -m "docs(reports): document the Reportes module in CLAUDE.md"
```

---

## Note on scope: frontend is a separate plan

This plan covers the Laravel API only. The spec's Next.js frontend section (sidebar menu, 6 report
pages, filters-in-URL, paginated table, Excel download button) is a genuinely separate subsystem —
different repo, different stack, and it can only be built and tested meaningfully against a
working API. Once this backend plan is merged, run `writing-plans` again for
`frontend-cm` to produce `docs/superpowers/plans/<date>-reports-module-frontend.md`, exploring
`frontend-cm`'s existing table/filter components and the "Administración de Contenido" module first
so the frontend plan follows established patterns instead of guessing at them.
