<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\User;
use App\Services\WhatsAppClient;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class CarnetController extends Controller
{
    public function __construct(private WhatsAppClient $whatsapp)
    {
    }

    public function send($id)
    {
        $affiliate = Affiliate::with('beneficiaries')->find($id);
        if (!$affiliate) {
            return response()->json(['message' => 'Afiliado no encontrado'], 404);
        }

        if (empty($affiliate->movil) || !preg_match('/^\d{10}$/', $affiliate->movil)) {
            return response()->json(['message' => 'El celular del afiliado no es válido'], 422);
        }

        $settings = $this->whatsapp->configurationForTemplate('wa_template_name');
        if (!$settings) {
            return response()->json(['message' => 'Configuración de WhatsApp incompleta'], 500);
        }

        $franchises = User::where('state', 1)->where('type', 2)->get(['name', 'movil']);

        if (empty($affiliate->validity_end)) {
            return response()->json(['message' => 'El afiliado no tiene fecha de vencimiento registrada'], 422);
        }

        try {
            $filename     = "carnet_{$id}_" . time() . ".pdf";
            $relativePath = "carnets/{$filename}";
            $absolutePath = storage_path("app/public/carnets/{$filename}");

            Storage::disk('public')->makeDirectory('carnets');
            $this->generatePdf($affiliate, $franchises, $absolutePath);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al generar el carnet'], 500);
        }

        $pdfUrl   = config('app.url') . '/storage/' . $relativePath;
        $fullName = strtoupper($affiliate->name . ' ' . $affiliate->lastname);

        $components = [
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
                    'text' => $fullName,
                ]],
            ],
        ];

        $result = $this->whatsapp->sendTemplate(
            $affiliate->movil,
            $settings->wa_template_name,
            $components,
            'carnet',
        );

        if ($result['enviado']) {
            $affiliate->carnet = 'si';
            $affiliate->save();

            return response()->json([
                'message' => 'Carnet enviado exitosamente',
                'data'    => $result['response'],
            ], 200);
        }

        return response()->json([
            'message' => 'Envío fallido',
            'error'   => $result['response'] ?? $result['detalle'] ?? null,
        ], 422);
    }

    private function generatePdf(Affiliate $affiliate, $franchises, string $savePath): void
    {
        $pdf = new Fpdi();
        $pdf->SetCreator('Contacto Médico');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pdf->setSourceFile(resource_path('pdf/carnet.pdf'));
        $tplId = $pdf->importPage(1);
        $size  = $pdf->getTemplateSize($tplId);
        $W     = $size['width'];
        $H     = $size['height'];

        $pdf->AddPage('P', [$W, $H]);
        $pdf->useTemplate($tplId, 0, 0, $W, $H, true);

        // X scale factor: the original Zend reference used a 595pt page (A4 = 210mm)
        $sx = $W / 210.0;

        // "CREDENCIAL AFILIADO" title — ref: x=70, y=670, 26pt, red
        $pdf->SetFont('helvetica', 'B', 26);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(0, 180);
        $pdf->Cell($W, 0, 'C  R  E  D  E  N  C  I  A  L    A  F  I  L  I  A  D  O', 0, 0, 'C');

        // Affiliate name — ref: x=170, y=610 (670-60), 24pt, black
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(0, 0, 0);
        $nameText = mb_strtoupper($affiliate->name . ' ' . $affiliate->lastname, 'UTF-8');
        $nameW    = $pdf->GetStringWidth($nameText);
        $pdf->SetXY(($W - $nameW) / 2, 200);
        $pdf->Cell(0, 0, $nameText);

        // ID card number — centered like the name
        $ccText = 'CC. ' . $affiliate->id_card;
        $ccW    = $pdf->GetStringWidth($ccText);
        $pdf->SetXY(($W - $ccW) / 2, 210);
        $pdf->Cell(0, 0, $ccText);

        // "BENEFICIARIOS" label — ref: x=70, y=520 (580-60), 22pt, red
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(24.7 * $sx, 240);
        $pdf->Cell(0, 0, 'BENEFICIARIOS');

        // Beneficiary list — ref: x=70, y=480 (520-40), 25pt=8.8mm step, 22pt
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(0, 0, 0);
        $yBene = 255;
        foreach ($affiliate->beneficiaries as $bene) {
            $pdf->SetXY(24.7 * $sx, $yBene);
            $pdf->Cell(0, 0, mb_strtoupper($bene->name, 'UTF-8'));
            $yBene += 8.8;
        }

        // "VÁLIDO HASTA" (valid until) — ref: x=486, y=210, 22pt, blue.
        // Month names stay in Spanish — this text is printed on the card itself.
        $months = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO',    4 => 'ABRIL',
            5 => 'MAYO',  6 => 'JUNIO',   7 => 'JULIO',    8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        $monthNum = (int) date('n', strtotime($affiliate->validity_end));
        $month    = $months[$monthNum];
        $year     = date('d/y', strtotime($affiliate->validity_end));

        $xValidUntil = 140 * $sx;

        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(38, 198, 218);
        $pdf->SetXY($xValidUntil, 330);
        $pdf->Cell(0, 0, 'VÁLIDO HASTA');

        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY($xValidUntil, 340);
        $pdf->Cell(0, 0, $month);
        $xYear = $xValidUntil + $pdf->GetStringWidth($month) + 8;
        $pdf->SetXY($xYear, 340);
        $pdf->Cell(0, 0, $year);

        // Franchise footer — ref: 20pt, dynamic x starting at x=5, 3 per row, 25pt=8.8mm step
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 0, 0);

        $franchiseArr = $franchises->all();
        $total        = count($franchiseArr);
        $yFran        = 385;
        $xFran        = 1.76 * $sx;   // ref x=5pt

        foreach ($franchiseArr as $key => $fran) {
            $isLast     = ($key === $total - 1);
            $endOfRow   = (($key + 1) % 3 === 0);
            $sep        = (!$isLast && !$endOfRow) ? ' -' : '';

            $nameStr    = mb_strtoupper($fran->name, 'UTF-8');
            $displayStr = $nameStr . ' ' . $fran->movil . $sep;
            $pdf->SetXY($xFran, $yFran);
            $pdf->Cell(0, 0, $displayStr);

            $xFran += $pdf->GetStringWidth($displayStr) + 5;

            if ($endOfRow && !$isLast) {
                $xFran = 1.76 * $sx;
                $yFran += 8.8;
            }
        }

        $pdf->Output($savePath, 'F');
    }
}
