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

        $recipient      = '57' . $affiliate->movil;
        $pdfUrl         = config('app.url') . '/storage/' . $relativePath;
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

        // Título
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(22, 55);
        $pdf->Cell(0, 0, 'C  R  E  D  E  N  C  I  A  L    A  F  I  L  I  A  D  O');

        // Nombre completo
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(55, 72);
        $pdf->Cell(0, 0, $this->enc(strtoupper($affiliate->name . ' ' . $affiliate->lastname)));

        // Cédula
        $pdf->SetXY(57, 80);
        $pdf->Cell(0, 0, 'CC.  ' . $affiliate->id_card);

        // Beneficiarios label
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY(22, 95);
        $pdf->Cell(0, 0, 'BENEFICIARIOS');

        // Lista de beneficiarios
        $pdf->SetTextColor(0, 0, 0);
        $yBene = 107;
        foreach ($affiliate->beneficiaries as $bene) {
            $pdf->SetXY(22, $yBene);
            $pdf->Cell(0, 0, $this->enc(strtoupper($bene->name)));
            $yBene += 8;
        }

        // Válido hasta
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

        $mes  = $meses[(int) date('n', strtotime($affiliate->validity_end))];
        $anio = date('d/y', strtotime($affiliate->validity_end));

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetTextColor(38, 198, 218);
        $pdf->SetXY(152, 255);
        $pdf->Cell(0, 0, $this->enc('VÁLIDO HASTA'));

        $pdf->SetTextColor(231, 60, 60);
        $pdf->SetXY($xMes[$mes] ?? 145, 263);
        $pdf->Cell(0, 0, $mes);
        $pdf->SetXY(195, 263);
        $pdf->Cell(0, 0, $anio);

        // Franquicias en 3 columnas
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
