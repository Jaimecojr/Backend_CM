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

        if (empty($affiliate->validity_end)) {
            return response()->json(['message' => 'El afiliado no tiene fecha de vencimiento registrada'], 422);
        }

        try {
            $filename     = "carnet_{$id}_" . time() . ".pdf";
            $relativePath = "carnets/{$filename}";
            $absolutePath = storage_path("app/public/carnets/{$filename}");

            Storage::disk('public')->makeDirectory('carnets');
            $this->generarPdf($affiliate, $franchises, $absolutePath);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al generar el carnet'], 500);
        }

        $recipient = '57' . $affiliate->movil;
        $pdfUrl    = config('app.url') . '/storage/' . $relativePath;
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

        $apiUrl = "https://graph.facebook.com/{$settings->wa_api_version}/{$settings->wa_phone_number_id}/messages";
        try {
            $http         = Http::withToken($settings->wa_bearer_token);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }
            $response     = $http->post($apiUrl, $payload);
            $responseData = $response->json();
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al contactar la API de WhatsApp'], 500);
        }

        WhatsappMessage::create([
            'response'     => json_encode($responseData),
            'recipient_id' => $recipient,
            'deleted'      => 0,
            'type'         => 'carnet',
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
        $W     = $size['width'];
        $H     = $size['height'];

        $pdf->AddPage('P', [$W, $H]);
        $pdf->useTemplate($tplId, 0, 0, $W, $H, true);

        // Factor de escala x: referencia Zend usaba página de 595pt (A4 = 210mm)
        $sx = $W / 210.0;

        // CREDENCIAL AFILIADO — ref: x=70, y=670, 26pt, rojo
        $pdf->SetFont('helvetica', 'B', 26);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(0, 180);
        $pdf->Cell($W, 0, 'C  R  E  D  E  N  C  I  A  L    A  F  I  L  I  A  D  O', 0, 0, 'C');

        // NOMBRE — ref: x=170, y=610 (670-60), 24pt, negro
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(0, 0, 0);
        $nameText = mb_strtoupper($affiliate->name . ' ' . $affiliate->lastname, 'UTF-8');
        $nameW    = $pdf->GetStringWidth($nameText);
        $pdf->SetXY(($W - $nameW) / 2, 200);
        $pdf->Cell(0, 0, $nameText);

        // CÉDULA — centrada igual que el nombre
        $ccText = 'CC. ' . $affiliate->id_card;
        $ccW    = $pdf->GetStringWidth($ccText);
        $pdf->SetXY(($W - $ccW) / 2, 210);
        $pdf->Cell(0, 0, $ccText);

        // BENEFICIARIOS label — ref: x=70, y=520 (580-60), 22pt, rojo
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(24.7 * $sx, 240);
        $pdf->Cell(0, 0, 'BENEFICIARIOS');

        // LISTA BENEFICIARIOS — ref: x=70, y=480 (520-40), paso 25pt=8.8mm, 22pt
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(0, 0, 0);
        $yBene = 255;
        foreach ($affiliate->beneficiaries as $bene) {
            $pdf->SetXY(24.7 * $sx, $yBene);
            $pdf->Cell(0, 0, mb_strtoupper($bene->name, 'UTF-8'));
            $yBene += 8.8;
        }

        // VÁLIDO HASTA — ref: x=486, y=210, 22pt, azul
        $meses = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO',    4 => 'ABRIL',
            5 => 'MAYO',  6 => 'JUNIO',   7 => 'JULIO',    8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        $mesNum = (int) date('n', strtotime($affiliate->validity_end));
        $mes    = $meses[$mesNum];
        $anio   = date('d/y', strtotime($affiliate->validity_end));

        $xValido = 140 * $sx;

        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(38, 198, 218);
        $pdf->SetXY($xValido, 330);
        $pdf->Cell(0, 0, 'VÁLIDO HASTA');

        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY($xValido, 340);
        $pdf->Cell(0, 0, $mes);
        $xAnio = $xValido + $pdf->GetStringWidth($mes) + 8;
        $pdf->SetXY($xAnio, 340);
        $pdf->Cell(0, 0, $anio);

        // FRANQUICIAS — ref: 20pt, x dinámico desde x=5, 3 por fila, paso 25pt=8.8mm
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 0, 0);

        $franArr = $franchises->all();
        $total   = count($franArr);
        $yFran   = 385;
        $xFran   = 1.76 * $sx;   // ref x=5pt

        foreach ($franArr as $key => $fran) {
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
