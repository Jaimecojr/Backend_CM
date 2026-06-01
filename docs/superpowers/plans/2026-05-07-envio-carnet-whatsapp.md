# Envío de Carnets por WhatsApp — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el envío del carnet PDF de afiliados por WhatsApp Cloud API, generando el PDF sobre una plantilla base con FPDI+TCPDF y registrando cada envío en `whatsapp_messages`.

**Architecture:** Nuevo `CarnetController` con método `send($id)` que valida el afiliado, genera el PDF sobre la plantilla base, llama a la API de Meta y registra el resultado. El frontend conecta el botón `IdCard` existente (columns.tsx) con un handler en page.tsx que usa SweetAlert2 para confirmación y resultado.

**Tech Stack:** Laravel 12 (PHP), setasign/fpdi + tecnickcom/tcpdf, WhatsApp Cloud API (Meta), Next.js 15 / React 19 / TypeScript, SweetAlert2.

---

## Mapa de archivos

| Acción | Archivo |
|---|---|
| Crear | `app/Http/Controllers/CarnetController.php` |
| Crear | `resources/pdf/carnet.pdf` (copiar plantilla base) |
| Modificar | `routes/api.php` — agregar ruta POST |
| Modificar | `composer.json` — instalar fpdi + tcpdf |
| Modificar | `frontend-cm/src/app/4dnn1n/affiliates/fetch.ts` — agregar `sendCarnet()` |
| Modificar | `frontend-cm/src/app/4dnn1n/affiliates/page.tsx` — handler `onSendCarnet` |
| Modificar | `frontend-cm/src/app/4dnn1n/affiliates/_components/columns.tsx` — conectar botón |

---

## Task 1: Instalar dependencias PHP y copiar plantilla PDF

**Files:**
- Modify: `composer.json`
- Create: `resources/pdf/carnet.pdf`

- [ ] **Step 1: Instalar FPDI y TCPDF**

Ejecutar en `api-cm/`:
```bash
composer require setasign/fpdi tecnickcom/tcpdf
```

Salida esperada: `Package operations: 2 installs` (o similar). Verificar que `vendor/setasign/fpdi` y `vendor/tecnickcom/tcpdf` existen.

- [ ] **Step 2: Crear directorio y copiar la plantilla PDF**

```bash
mkdir -p resources/pdf
```

Copiar el archivo `carnet.pdf` (el adjunto del cliente) a `resources/pdf/carnet.pdf`. Verificar:
```bash
ls resources/pdf/carnet.pdf
```

- [ ] **Step 3: Verificar que Laravel puede leer la plantilla**

```bash
php artisan tinker --execute="echo file_exists(resource_path('pdf/carnet.pdf')) ? 'OK' : 'NO ENCONTRADO';"
```

Salida esperada: `OK`

---

## Task 2: Crear CarnetController (backend)

**Files:**
- Create: `app/Http/Controllers/CarnetController.php`

- [ ] **Step 1: Crear el archivo del controlador**

Crear `app/Http/Controllers/CarnetController.php` con el siguiente contenido:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class CarnetController extends Controller
{
    public function send($id)
    {
        $affiliate = Affiliate::with('beneficiaries')->find($id);
        if (!$affiliate) {
            return response()->json(['message' => 'Afiliado no encontrado'], 404);
        }

        if (empty($affiliate->movil) || !preg_match('/^\d{10}$/', $affiliate->movil)) {
            return response()->json(['message' => 'El celular del afiliado no es válido'], 422);
        }

        $settings = Setting::first();
        if (
            !$settings ||
            empty($settings->wa_api_version) ||
            empty($settings->wa_phone_number_id) ||
            empty($settings->wa_bearer_token) ||
            empty($settings->wa_template_name)
        ) {
            return response()->json(['message' => 'Configuración de WhatsApp incompleta'], 500);
        }

        $franchises = User::where('state', 1)->where('type', 2)->get(['name', 'movil']);

        try {
            $filename     = "carnet_{$id}_" . time() . ".pdf";
            $relativePath = "carnets/{$filename}";
            $absolutePath = storage_path("app/public/carnets/{$filename}");

            Storage::disk('public')->makeDirectory('carnets');
            $this->generarPdf($affiliate, $franchises, $absolutePath);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al generar el carnet'], 500);
        }

        $recipient     = '57' . $affiliate->movil;
        $pdfUrl        = config('app.url') . '/storage/' . $relativePath;
        $nombreCompleto = strtoupper($affiliate->name . ' ' . $affiliate->lastname);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $recipient,
            'type'              => 'template',
            'template'          => [
                'name'       => $settings->wa_template_name,
                'language'   => ['code' => 'es'],
                'components' => [
                    [
                        'type'       => 'header',
                        'parameters' => [[
                            'type'     => 'document',
                            'document' => [
                                'link'     => $pdfUrl,
                                'filename' => 'carnet.pdf',
                            ],
                        ]],
                    ],
                    [
                        'type'       => 'body',
                        'parameters' => [[
                            'type' => 'text',
                            'text' => $nombreCompleto,
                        ]],
                    ],
                ],
            ],
        ];

        $apiUrl   = "https://graph.facebook.com/{$settings->wa_api_version}/{$settings->wa_phone_number_id}/messages";
        $response = Http::withToken($settings->wa_bearer_token)->post($apiUrl, $payload);
        $responseData = $response->json();

        WhatsappMessage::create([
            'response'     => json_encode($responseData),
            'recipient_id' => $recipient,
            'deleted'      => 0,
        ]);

        if (!empty($responseData['messages'][0]['id'])) {
            $affiliate->carnet = 'si';
            $affiliate->save();

            return response()->json([
                'message' => 'Carnet enviado exitosamente',
                'data'    => $responseData,
            ], 200);
        }

        return response()->json([
            'message' => 'Envío fallido',
            'error'   => $responseData,
        ], 422);
    }

    private function generarPdf(Affiliate $affiliate, $franchises, string $savePath): void
    {
        $pdf = new Fpdi();
        $pdf->SetCreator('Contacto Médico');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pdf->setSourceFile(resource_path('pdf/carnet.pdf'));
        $tplId = $pdf->importPage(1);
        $size  = $pdf->getTemplateSize($tplId);

        $pdf->AddPage('P', [$size['width'], $size['height']]);
        $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);

        // ── Título ──────────────────────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(22, 55);
        $pdf->Cell(0, 0, 'C  R  E  D  E  N  C  I  A  L    A  F  I  L  I  A  D  O');

        // ── Nombre completo ──────────────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(55, 72);
        $pdf->Cell(0, 0, $this->enc(strtoupper($affiliate->name . ' ' . $affiliate->lastname)));

        // ── Cédula ──────────────────────────────────────────────────────────
        $pdf->SetXY(57, 80);
        $pdf->Cell(0, 0, 'CC.  ' . $affiliate->id_card);

        // ── Beneficiarios ────────────────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(22, 95);
        $pdf->Cell(0, 0, 'BENEFICIARIOS');

        $pdf->SetTextColor(0, 0, 0);
        $yBene = 107;
        foreach ($affiliate->beneficiaries as $bene) {
            $pdf->SetXY(22, $yBene);
            $pdf->Cell(0, 0, $this->enc(strtoupper($bene->name)));
            $yBene += 8;
        }

        // ── Válido hasta ─────────────────────────────────────────────────────
        $meses = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO',    4 => 'ABRIL',
            5 => 'MAYO',  6 => 'JUNIO',   7 => 'JULIO',    8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];
        $xMes = [
            'ENERO' => 165, 'MARZO' => 165,
            'FEBRERO' => 155, 'OCTUBRE' => 155,
            'ABRIL' => 170, 'MAYO' => 170,
            'JUNIO' => 168, 'JULIO' => 168,
            'AGOSTO' => 158, 'SEPTIEMBRE' => 143,
            'NOVIEMBRE' => 147, 'DICIEMBRE' => 149,
        ];

        $mes     = $meses[(int) date('n', strtotime($affiliate->validity_end))];
        $anio    = date('d/y', strtotime($affiliate->validity_end));

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(38, 198, 218);
        $pdf->SetXY(152, 255);
        $pdf->Cell(0, 0, $this->enc('VÁLIDO HASTA'));

        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY($xMes[$mes] ?? 145, 263);
        $pdf->Cell(0, 0, $mes);
        $pdf->SetXY(195, 263);
        $pdf->Cell(0, 0, $anio);

        // ── Franquicias (3 columnas) ─────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 6);
        $pdf->SetTextColor(0, 0, 0);

        $colWidth = 68;
        $xFran    = 2;
        $yFran    = 285;

        foreach ($franchises as $i => $fran) {
            $texto = $this->enc(strtoupper($fran->name)) . ' ' . $fran->movil;
            $pdf->SetXY($xFran, $yFran);
            $pdf->Cell($colWidth, 0, $texto, 0, 0);

            if (($i + 1) % 3 === 0) {
                $xFran = 2;
                $yFran += 8;
            } else {
                $xFran += $colWidth;
            }
        }

        $pdf->Output($savePath, 'F');
    }

    private function enc(string $text): string
    {
        return iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
    }
}
```

> **Nota sobre coordenadas:** Los valores de `SetXY` son aproximados basados en la conversión del sistema anterior (Zend_Pdf usa puntos desde abajo-izquierda; TCPDF usa mm desde arriba-izquierda). Tras implementar, generar un PDF de prueba con un afiliado real y ajustar los valores de y hasta que el texto quede alineado con la plantilla visual.

- [ ] **Step 2: Verificar sintaxis PHP**

```bash
php -l app/Http/Controllers/CarnetController.php
```

Salida esperada: `No syntax errors detected`

---

## Task 3: Registrar ruta en api.php

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Agregar import del controlador y la ruta**

En `routes/api.php`, agregar el import al inicio (junto a los demás):
```php
use App\Http\Controllers\CarnetController;
```

Agregar la ruta dentro del grupo `auth:sanctum`, inmediatamente **antes** de `Route::apiResource('affiliates', ...)`:

```php
// Carnet de afiliados
Route::post('affiliates/{id}/carnet', [CarnetController::class, 'send']);
```

El bloque de afiliados debe quedar así:
```php
//Afiliados / Usuarios
Route::get('affiliates/check-id-card',   [AffiliateController::class, 'checkIdCard']);
Route::get('affiliates/expiring-today',  [AffiliateController::class, 'expiringToday']);
Route::post('affiliates/{id}/carnet',    [CarnetController::class, 'send']);  // ← nueva
Route::apiResource('affiliates', AffiliateController::class);
```

- [ ] **Step 2: Verificar que la ruta está registrada**

```bash
php artisan route:list --path=affiliates
```

Debe aparecer una línea con `POST affiliates/{id}/carnet` apuntando a `CarnetController@send`.

- [ ] **Step 3: Verificar que el servidor arranca sin errores**

```bash
php artisan config:clear && php artisan route:clear
php artisan serve --port=8000
```

Salida esperada: `INFO Server running on [http://127.0.0.1:8000]`. Cerrar con Ctrl+C.

---

## Task 4: Agregar sendCarnet() en el frontend (fetch.ts)

**Files:**
- Modify: `frontend-cm/src/app/4dnn1n/affiliates/fetch.ts`

- [ ] **Step 1: Agregar la función al final del archivo**

Al final de `fetch.ts`, después de `deleteAffiliateNote`, agregar:

```typescript
// Envío de carnet por WhatsApp
export async function sendCarnet(id: number): Promise<{ message: string; data?: unknown; error?: unknown }> {
  await csrf();
  return apiFetch<{ message: string; data?: unknown; error?: unknown }>(
    `/api/affiliates/${id}/carnet`,
    { method: "POST" },
  );
}
```

- [ ] **Step 2: Verificar que el archivo compila**

Desde `frontend-cm/`:
```bash
npx tsc --noEmit
```

Salida esperada: sin errores (o solo errores preexistentes no relacionados con `fetch.ts`).

---

## Task 5: Agregar handler onSendCarnet en page.tsx

**Files:**
- Modify: `frontend-cm/src/app/4dnn1n/affiliates/page.tsx`

- [ ] **Step 1: Agregar import de sendCarnet**

En la línea que ya importa de `./fetch`, agregar `sendCarnet`:

```typescript
import { getAffiliates, updateAffiliateState, sendCarnet, type ApiAffiliate } from "./fetch";
```

- [ ] **Step 2: Agregar el handler onSendCarnet**

Después de la función `onToggleState` (approx. línea 62), agregar:

```typescript
const onSendCarnet = async (c: ApiAffiliate) => {
  const ok = await alert.confirm({
    title: "¿Enviar carnet?",
    text: `Se enviará el carnet de ${c.name} ${c.lastname} por WhatsApp al número ${c.movil}.`,
    confirmButtonText: "Sí, enviar",
    cancelButtonText: "Cancelar",
    icon: "question",
  });

  if (!ok) return;

  try {
    await sendCarnet(c.id);
    setData((prev) => prev.map((x) => (x.id === c.id ? { ...x, carnet: "si" as const } : x)));
    await alert.success("Carnet enviado", "El carnet fue enviado exitosamente por WhatsApp.");
  } catch (err) {
    await alert.error("Envío fallido", getApiErrorMessage(err));
  }
};
```

- [ ] **Step 3: Pasar onSendCarnet a buildAffiliateColumns**

Localizar la llamada a `buildAffiliateColumns` (approx. línea 64-72) y agregarle el nuevo prop:

```typescript
const columns = useMemo(
  () => buildAffiliateColumns({
    onToggleState,
    onSendCarnet,           // ← agregar
    onAddNote: (c) => setNoteTarget(c),
    hasAccess,
    canToggle,
  }),
  // eslint-disable-next-line react-hooks/exhaustive-deps
  [hasAccess, canToggle, stadeFilter],
);
```

- [ ] **Step 4: Verificar que compila**

```bash
npx tsc --noEmit
```

Salida esperada: errores solo sobre `onSendCarnet` en `columns.tsx` (porque aún no lo recibe — se resuelve en el siguiente task).

---

## Task 6: Conectar botón IdCard en columns.tsx

**Files:**
- Modify: `frontend-cm/src/app/4dnn1n/affiliates/_components/columns.tsx`

- [ ] **Step 1: Actualizar la firma de buildAffiliateColumns**

Cambiar la definición del parámetro de la función para incluir `onSendCarnet`:

```typescript
export function buildAffiliateColumns({
  onToggleState,
  onSendCarnet,
  onAddNote,
  hasAccess,
  canToggle,
}: {
  onToggleState: (c: ApiAffiliate) => void;
  onSendCarnet: (c: ApiAffiliate) => void;   // ← agregar
  onAddNote: (c: ApiAffiliate) => void;
  hasAccess: boolean;
  canToggle: boolean;
}): ColumnDef<ApiAffiliate>[] {
```

- [ ] **Step 2: Actualizar el botón IdCard**

Reemplazar el bloque del botón IdCard actual (approx. líneas 131-140):

```typescript
// Antes:
{c.carnet === "no" && (
  <button
    type="button"
    className="hover:bg-muted rounded-md p-2"
    title="Carnet"
    aria-label="Carnet"
  >
    <IdCard className="h-4 w-4 text-teal-600" />
  </button>
)}
```

Por:

```typescript
// Después:
{c.carnet === "no" && /^\d{10}$/.test(c.movil ?? "") && (
  <button
    type="button"
    className="hover:bg-muted rounded-md p-2"
    title="Enviar carnet por WhatsApp"
    aria-label="Enviar carnet por WhatsApp"
    onClick={() => onSendCarnet(c)}
  >
    <IdCard className="h-4 w-4 text-teal-600" />
  </button>
)}
```

- [ ] **Step 3: Verificar que compila sin errores**

```bash
npx tsc --noEmit
```

Salida esperada: sin errores de TypeScript.

- [ ] **Step 4: Verificar visualmente en el navegador**

Iniciar el servidor de desarrollo del frontend:
```bash
npm run dev
```

1. Ir a la lista de afiliados (`/4dnn1n/affiliates`).
2. Buscar un afiliado con `carnet = 'no'` y `movil` de 10 dígitos — debe mostrar el ícono `IdCard` en teal.
3. Un afiliado con `carnet = 'si'` NO debe mostrar el ícono.
4. Un afiliado con `movil` vacío o incorrecto NO debe mostrar el ícono.
5. Hacer clic en el ícono → debe aparecer el SweetAlert de confirmación.
6. Cancelar → no ocurre nada.
7. (El envío real requiere settings de WA configurados en producción.)

---

## Notas de ajuste post-deploy

### Coordenadas del PDF
Las coordenadas en `generarPdf()` son una conversión aproximada del sistema anterior. Tras el primer despliegue, generar un PDF de prueba llamando al endpoint con un afiliado real y ajustar los valores de `SetXY` hasta que el texto quede correctamente posicionado sobre la plantilla visual.

Comando para probar localmente (con servidor corriendo):
```bash
curl -X POST http://localhost:8000/api/affiliates/{id}/carnet \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

El PDF se guarda en `storage/app/public/carnets/` — abrirlo y verificar visualmente.

### Storage link en Railway
Si aún no está configurado, agregar en el proceso de build/start:
```bash
php artisan storage:link
```

O agregar al `Procfile` / comando de inicio en Railway antes del `php artisan serve`.

### Encoding de caracteres
La función `enc()` convierte UTF-8 a windows-1252 para que las fuentes estándar de TCPDF (helvetica) rendericen correctamente tildes y caracteres especiales del español. Si en el PDF aparecen caracteres raros, verificar que el nombre/apellido del afiliado llega como UTF-8 válido desde la base de datos.
