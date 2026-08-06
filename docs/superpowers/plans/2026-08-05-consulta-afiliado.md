# Consulta de Estado de Afiliado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a visitor on the public website enter a cédula and see, in a modal, whether their family affiliation group is active — the titular's name, cédula, beneficiaries, and expiration date — closing out the last missing piece of the public site.

**Architecture:** A new public read-only Laravel endpoint (`POST /api/public/affiliate-status`) looks up an `Affiliate` by `id_card` with its `beneficiaries`, exposing only safe fields and never blocking on inactive/expired status (unlike the existing internal `byIdCard`). The existing `AffiliateConsultWidget.tsx` on the frontend is trimmed down (document type fixed to cédula, document number digit-only) and, on submit, opens a new `AffiliateStatusModal.tsx` that renders the result — found (active or inactive) or not-found — following the visual pattern already established by `LegalModal.tsx`.

**Tech Stack:** Laravel (PHP, MySQL) for the backend; Next.js/React + Tailwind CSS + `dayjs` for the frontend. No new dependencies.

## Global Constraints

- Public endpoints never expose `movil`, `phone`, `email`, or other internal affiliate fields (project-wide rule for everything under `/api/public/*`).
- Public routes are registered inside the existing `Route::prefix('public')->group(...)` in `routes/api.php`, before the `auth:sanctum` group.
- Comments, validation messages, and JSON response strings are in Spanish.
- The existing internal endpoint `AffiliateController::byIdCard` (used by the appointment-creation flow) must not be modified or reused — the new public endpoint is a separate method.
- `document_number` must accept digits only; enforced both in the frontend input (strip non-digits on change) and in backend validation (regex).
- The new public endpoint always returns `200` with data when the affiliate exists, regardless of `stade`/`validity_end` — the frontend decides how to display active vs. inactive, it is never blocked server-side like `byIdCard` does.
- This is a UI-facing change — before marking the work done, the dev servers must be started and the golden path plus edge cases exercised in a real browser (per project convention for frontend changes).

---

### Task 1: Public backend endpoint `POST /api/public/affiliate-status`

**Files:**
- Modify: `app/Http/Controllers/AffiliateController.php` (add `publicStatus` method after `byIdCard`, currently ending at line 347)
- Modify: `routes/api.php:36` (add route inside the existing `public` group, right after `affiliate-request`)
- Create: `tests/Feature/AffiliatePublicStatusTest.php`

**Interfaces:**
- Produces: `AffiliateController::publicStatus(Request $request)` → JSON `{ success: bool, message: string, data?: { id, name, lastname, id_card, stade, validity_end, beneficiaries: [{id, affiliate_id, name}] } }`. Route name: `POST /api/public/affiliate-status`.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/AffiliatePublicStatusTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliatePublicStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_encuentra_afiliado_activo_con_beneficiarios(): void
    {
        $affiliate = Affiliate::factory()->create([
            'name'         => 'Jaime',
            'lastname'     => 'Castaño',
            'id_card'      => '1094947820',
            'stade'        => 1,
            'validity_end' => Carbon::today()->addMonths(6)->toDateString(),
        ]);

        Beneficiary::create(['affiliate_id' => $affiliate->id, 'name' => 'Manuela Ocampo', 'id_card' => '1000000001']);
        Beneficiary::create(['affiliate_id' => $affiliate->id, 'name' => 'Juan Carlos Ocampo', 'id_card' => '1000000002']);

        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '1094947820',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.name', 'Jaime')
                 ->assertJsonPath('data.lastname', 'Castaño')
                 ->assertJsonPath('data.stade', 1)
                 ->assertJsonCount(2, 'data.beneficiaries');

        $this->assertArrayNotHasKey('movil', $response->json('data'));
        $this->assertArrayNotHasKey('phone', $response->json('data'));
    }

    public function test_encuentra_afiliado_inactivo_y_no_lo_bloquea(): void
    {
        Affiliate::factory()->create([
            'id_card'      => '2094947820',
            'stade'        => 2,
            'validity_end' => Carbon::today()->subMonths(2)->toDateString(),
        ]);

        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '2094947820',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.stade', 2);
    }

    public function test_retorna_404_si_no_existe(): void
    {
        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '9999999999',
        ]);

        $response->assertStatus(404)
                 ->assertJsonPath('success', false);
    }

    public function test_retorna_422_sin_document_number(): void
    {
        $response = $this->postJson('/api/public/affiliate-status', []);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    public function test_retorna_422_con_document_number_no_numerico(): void
    {
        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '109.494-7820',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AffiliatePublicStatusTest`
Expected: FAIL — route `/api/public/affiliate-status` does not exist (404 on every test, including the ones expecting 404, since the assertion on `success` JSON path will fail with no matching route registered, or a route-not-found response without the expected JSON body).

- [ ] **Step 3: Add the route**

In `routes/api.php`, inside the existing `public` group (around line 36), add the new route right after the `affiliate-request` line:

```php
    Route::post('affiliate-request', [MembershipFormController::class, 'store']);
    Route::post('affiliate-status', [AffiliateController::class, 'publicStatus']);
```

`AffiliateController` is already imported at the top of the file (line 3), no new `use` statement needed.

- [ ] **Step 4: Implement `publicStatus()`**

In `app/Http/Controllers/AffiliateController.php`, add this method right after `byIdCard()` (before the closing `}` of the class, currently at line 347):

```php
    /**
     * Consulta pública de estado de un afiliado y su grupo familiar por cédula.
     * A diferencia de byIdCard() (uso interno para crear citas), no bloquea
     * afiliados inactivos o vencidos: siempre retorna los datos si el
     * registro existe, para que el sitio público muestre el aviso de estado.
     */
    public function publicStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required|string|regex:/^[0-9]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ingresa un número de documento válido.',
            ], 422);
        }

        $affiliate = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'stade', 'validity_end'])
            ->with(['beneficiaries:id,affiliate_id,name'])
            ->where('id_card', $request->input('document_number'))
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos un grupo familiar con esa cédula.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Afiliado encontrado.',
            'data'    => $affiliate,
        ], 200);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=AffiliatePublicStatusTest`
Expected: PASS (5 tests, all green).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/AffiliateController.php routes/api.php tests/Feature/AffiliatePublicStatusTest.php
git commit -m "feat: endpoint publico de consulta de estado de afiliado"
```

---

### Task 2: Frontend service layer — `affiliateService.ts`

**Files:**
- Modify: `src/services/affiliateService.ts` (full rewrite, currently 45 lines)

**Interfaces:**
- Consumes: backend response shape from Task 1 — `{ success, message, data?: { name, lastname, id_card, stade, validity_end, beneficiaries: [{name}] } }`.
- Produces: `export interface AffiliateStatusResponse` and `export async function checkAffiliateStatus(docNum: string): Promise<AffiliateStatusResponse>` — consumed by Task 3 and Task 4.

- [ ] **Step 1: Rewrite the service file**

Replace the full contents of `src/services/affiliateService.ts`:

```ts
// src/services/affiliateService.ts

export interface AffiliateStatusResponse {
  success: boolean;
  message: string;
  data?: {
    name: string;
    lastname: string;
    id_card: string;
    stade: number; // 1 = Activo, 2 = Inactivo
    validity_end: string;
    beneficiaries: { name: string }[];
  };
}

export async function checkAffiliateStatus(
  docNum: string
): Promise<AffiliateStatusResponse> {
  try {
    const response = await fetch(
      `${process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000"}/api/public/affiliate-status`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ document_number: docNum }),
      }
    );

    const data = await response.json();

    if (!response.ok) {
      return {
        success: false,
        message: data.message || "No se pudo consultar el estado. Intente nuevamente.",
      };
    }

    return data;
  } catch (error) {
    console.error("Error checking affiliate status:", error);
    return {
      success: false,
      message: "Ocurrió un error al consultar. Intente nuevamente.",
    };
  }
}
```

Note: the previous version threw on non-2xx responses and never read the body, which discarded the backend's `message` (e.g. the 404 "no encontramos" text or 422 validation text). This version reads the JSON body in both the ok and not-ok cases so that message reaches the modal.

- [ ] **Step 2: Verify TypeScript compiles**

Run (in `frontend-cm`): `npx tsc --noEmit`
Expected: no new errors from `affiliateService.ts`. (Task 4 will still reference the old `checkAffiliateStatus(docType, docNum)` two-argument signature at this point — that mismatch is expected and gets fixed in Task 4. If running this in isolation produces an error in `AffiliateConsultWidget.tsx`, that's fine; it's fixed by the next task.)

- [ ] **Step 3: Commit**

```bash
git add src/services/affiliateService.ts
git commit -m "feat: actualizar servicio de consulta de afiliado con beneficiarios"
```

---

### Task 3: Frontend form — `AffiliateConsultWidget.tsx`

**Files:**
- Modify: `src/components/web/AffiliateConsultWidget.tsx` (full rewrite, currently 97 lines)

**Interfaces:**
- Consumes: `checkAffiliateStatus(docNum: string): Promise<AffiliateStatusResponse>` from Task 2; `AffiliateStatusModal` component (props `{ result: AffiliateStatusResponse; onClose: () => void }`) from Task 4 — this task can be implemented and manually checked with a temporary inline placeholder for the modal import, but the final import must point at `@/components/web/AffiliateStatusModal` created in Task 4. Since Task 4 creates that exact file/export, do this task's import as final code now; the app will not build until Task 4 exists — that's expected, this plan is meant to be executed task-by-task in order.
- Produces: nothing new consumed by later tasks besides being the modal's trigger.

- [ ] **Step 1: Rewrite the widget**

Replace the full contents of `src/components/web/AffiliateConsultWidget.tsx`:

```tsx
"use client";

import React, { useState } from "react";
import { checkAffiliateStatus, AffiliateStatusResponse } from "@/services/affiliateService";
import { AffiliateStatusModal } from "@/components/web/AffiliateStatusModal";

export function AffiliateConsultWidget() {
  const [docNum, setDocNum] = useState("");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<AffiliateStatusResponse | null>(null);

  const handleDocNumChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setDocNum(e.target.value.replace(/\D/g, ""));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      const response = await checkAffiliateStatus(docNum);
      setResult(response);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="w-full max-w-md bg-white rounded-xl shadow-lg p-8 border border-[#e5eeff]">
      <h3 className="font-semibold text-2xl mb-6 text-[#1A1A2E]">
        Consulta de Afiliado
      </h3>
      <form className="space-y-6" onSubmit={handleSubmit}>
        <div>
          <label className="block text-sm font-medium text-[#64748B] mb-2">
            Tipo de Documento
          </label>
          <select
            className="w-full bg-[#f8faff] border border-[#e5eeff] rounded-xl py-3 px-4 text-[#64748B] outline-none disabled:cursor-not-allowed"
            value="CC"
            disabled
          >
            <option value="CC">Cédula de Ciudadanía</option>
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-[#64748B] mb-2">
            Número de Documento
          </label>
          <input
            className="w-full bg-[#ffffff] border border-[#e5eeff] rounded-xl py-3 px-4 focus:ring-2 focus:ring-[#1DBFCE] transition-all outline-none"
            placeholder="Ej. 1094947820"
            type="text"
            inputMode="numeric"
            pattern="[0-9]*"
            maxLength={20}
            value={docNum}
            onChange={handleDocNumChange}
            required
          />
        </div>

        <button
          className="w-full py-4 bg-[#1DBFCE] text-white rounded-xl font-semibold text-base shadow-md hover:bg-[#1DBFCE]/90 transition-all uppercase tracking-wide disabled:opacity-50"
          type="submit"
          disabled={loading || docNum.length === 0}
        >
          {loading ? "Consultando..." : "Consultar Estado"}
        </button>
      </form>

      {result && (
        <AffiliateStatusModal result={result} onClose={() => setResult(null)} />
      )}
    </div>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add src/components/web/AffiliateConsultWidget.tsx
git commit -m "feat: fijar tipo de documento y filtrar numero de documento en consulta de afiliado"
```

(This commit will not build cleanly in isolation until Task 4 adds `AffiliateStatusModal.tsx` — that's expected; verify the full build at the end of Task 4.)

---

### Task 4: Frontend result modal — `AffiliateStatusModal.tsx`

**Files:**
- Create: `src/components/web/AffiliateStatusModal.tsx`

**Interfaces:**
- Consumes: `AffiliateStatusResponse` type from `src/services/affiliateService.ts` (Task 2).
- Produces: `export function AffiliateStatusModal(props: { result: AffiliateStatusResponse; onClose: () => void }): JSX.Element` — consumed by `AffiliateConsultWidget.tsx` (Task 3).

- [ ] **Step 1: Before writing styles, invoke the frontend-design skill**

Run the `frontend-design` skill (`/frontend-design:frontend-design`) for guidance on visual polish, spacing, and responsive layout before finalizing this component's JSX/classes below — the user explicitly asked for this modal to look polished, not just functional. Use the skill's guidance to adjust colors/spacing/typography from the baseline below as needed, keeping the `#1DBFCE` brand accent and the `LegalModal.tsx` portal/backdrop/escape mechanics intact.

- [ ] **Step 2: Create the component**

Create `src/components/web/AffiliateStatusModal.tsx`:

```tsx
"use client";

import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import dayjs from "dayjs";
import { AffiliateStatusResponse } from "@/services/affiliateService";

interface AffiliateStatusModalProps {
  result: AffiliateStatusResponse;
  onClose: () => void;
}

export function AffiliateStatusModal({ result, onClose }: AffiliateStatusModalProps) {
  const [mounted, setMounted] = useState(false);
  useEffect(() => {
    setMounted(true);
  }, []);

  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  });

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === "Escape") onCloseRef.current();
    };
    document.addEventListener("keydown", handleEscape);
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", handleEscape);
      document.body.style.overflow = "";
    };
  }, []);

  if (!mounted) return null;

  const data = result.data;
  const activa = !!data && data.stade === 1 && !dayjs(data.validity_end).isBefore(dayjs(), "day");

  return createPortal(
    <div
      className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
      onClick={onClose}
    >
      <div
        className="bg-white rounded-2xl shadow-2xl w-full max-w-md sm:max-w-lg flex flex-col max-h-[90vh]"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b-2 border-[#1DBFCE]">
          <h2 className="text-base font-bold text-[#1A1A2E]">Registro Grupo Familiar</h2>
          <button
            type="button"
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors ml-4 shrink-0"
            aria-label="Cerrar"
          >
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>

        {/* Body */}
        <div className="overflow-y-auto px-6 py-5 flex-1">
          {result.success && data ? (
            <div className="space-y-5">
              <div className="text-center">
                <h3 className="text-xs font-semibold uppercase tracking-wide text-[#1DBFCE]">
                  Titular
                </h3>
                <p className="mt-1 text-lg font-bold text-[#1A1A2E]">
                  {data.name} {data.lastname}
                </p>
                <p className="text-sm text-[#64748B]">CC. {data.id_card}</p>
              </div>

              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wide text-[#1DBFCE] mb-2">
                  Beneficiarios
                </h3>
                {data.beneficiaries.length > 0 ? (
                  <ul className="overflow-hidden rounded-xl border border-[#e5eeff]">
                    {data.beneficiaries.map((b, i) => (
                      <li
                        key={i}
                        className={`px-4 py-2 text-sm text-[#1A1A2E] ${
                          i % 2 === 0 ? "bg-white" : "bg-[#f8faff]"
                        }`}
                      >
                        {b.name}
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="text-sm italic text-[#64748B]">
                    Sin beneficiarios registrados.
                  </p>
                )}
              </div>

              <div
                className={`rounded-xl px-4 py-3 text-center text-sm font-semibold ${
                  activa ? "bg-[#eafff2] text-[#0a7d3c]" : "bg-[#ffdad6] text-[#93000a]"
                }`}
              >
                {activa
                  ? `Afiliación Activa — Vigente hasta ${dayjs(data.validity_end).format("DD/MM/YYYY")}`
                  : `Afiliación Inactiva — Venció ${dayjs(data.validity_end).format("MM/YYYY")}`}
              </div>
            </div>
          ) : (
            <div className="flex flex-col items-center gap-3 py-6 text-center">
              <span className="material-symbols-outlined text-4xl text-[#93000a]">
                error
              </span>
              <p className="text-sm font-medium text-[#93000a]">{result.message}</p>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-gray-100 flex justify-end">
          <button
            type="button"
            onClick={onClose}
            className="px-6 py-2 bg-[#1DBFCE] text-white font-semibold rounded-lg hover:opacity-90 transition-opacity text-sm"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>,
    document.body
  );
}
```

- [ ] **Step 3: Verify TypeScript compiles**

Run (in `frontend-cm`): `npx tsc --noEmit`
Expected: no errors involving `AffiliateStatusModal.tsx` or `AffiliateConsultWidget.tsx`.

- [ ] **Step 4: Commit**

```bash
git add src/components/web/AffiliateStatusModal.tsx
git commit -m "feat: modal de resultado de consulta de estado de afiliado"
```

---

### Task 5: Manual browser verification (golden path + edge cases)

**Files:** none (verification only).

**Interfaces:** Consumes the full stack built in Tasks 1-4.

- [ ] **Step 1: Seed test data via tinker**

From `api-cm`, run: `php artisan tinker`

```php
$a = \App\Models\Affiliate::factory()->create([
    'id_card' => '5551234567',
    'stade' => 1,
    'validity_end' => now()->addMonths(3)->toDateString(),
]);
\App\Models\Beneficiary::create(['affiliate_id' => $a->id, 'name' => 'Beneficiario Uno', 'id_card' => '5551000001']);
\App\Models\Beneficiary::create(['affiliate_id' => $a->id, 'name' => 'Beneficiario Dos', 'id_card' => '5551000002']);

$b = \App\Models\Affiliate::factory()->create([
    'id_card' => '5559876543',
    'stade' => 2,
    'validity_end' => now()->subMonths(1)->toDateString(),
]);
```

Note the two cédulas created: `5551234567` (active) and `5559876543` (inactive).

- [ ] **Step 2: Start both dev servers**

Run in `api-cm`: `php artisan serve`
Run in `frontend-cm`: `npm run dev`

- [ ] **Step 3: Test golden path in browser**

Open the public site home page. In the "Consulta de Afiliado" widget:
- Confirm "Tipo de Documento" shows "Cédula de Ciudadanía" and is disabled (not clickable/editable).
- In "Número de Documento", type `55.51,234a567` — confirm only `5551234567` appears in the field (letters, dots, comma stripped in real time).
- Submit. Confirm a modal opens titled "Registro Grupo Familiar" showing the titular's name, "CC. 5551234567", both beneficiaries listed, and a green "Afiliación Activa — Vigente hasta {fecha}" banner.
- Close the modal via the X button, reopen by submitting again, close via clicking the backdrop, and close via the Escape key — confirm all three work.

- [ ] **Step 4: Test edge cases**

- Submit with `5559876543` (inactive): confirm the modal shows the same titular layout but with a red "Afiliación Inactiva — Venció {mes}/{año}" banner instead of the green one.
- Submit with a cédula that doesn't exist (e.g. `1112223333`): confirm the modal opens showing "No encontramos un grupo familiar con esa cédula." instead of the family data.
- Stop the `api artisan serve` process temporarily and submit again: confirm the modal shows the generic network-error message instead of crashing the page. Restart `php artisan serve` afterward.

- [ ] **Step 5: Clean up seeded test data**

From `api-cm`, run: `php artisan tinker`

```php
\App\Models\Affiliate::whereIn('id_card', ['5551234567', '5559876543'])->each(function ($a) {
    $a->beneficiaries()->delete();
    $a->delete();
});
```

No commit for this task — it is verification only, confirming the feature works end-to-end before considering it done.
