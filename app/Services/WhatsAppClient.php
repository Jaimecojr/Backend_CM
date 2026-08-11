<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    /**
     * Envía un mensaje de plantilla (carnet, notificación de cita) y
     * registra el resultado en whatsapp_messages. El código de idioma es
     * siempre 'es_CO' — Meta rechaza 'es' con el error #132001.
     *
     * @param  array<int, array<string, mixed>>  $components  Componentes de la plantilla (header/body de Meta).
     * @return array{enviado: bool, response?: array, detalle?: string}
     */
    public function enviarPlantilla(string $telefono, string $templateName, array $components, string $tipoRegistro): array
    {
        $settings = Setting::first();

        if (
            !$settings ||
            empty($settings->wa_api_version) ||
            empty($settings->wa_phone_number_id) ||
            empty($settings->wa_bearer_token) ||
            empty($templateName)
        ) {
            return ['enviado' => false, 'detalle' => 'Configuración de WhatsApp incompleta'];
        }

        $recipient = '57' . $telefono;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $recipient,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => 'es_CO'],
                'components' => $components,
            ],
        ];

        $apiUrl = "https://graph.facebook.com/{$settings->wa_api_version}/{$settings->wa_phone_number_id}/messages";

        try {
            $http = Http::withToken($settings->wa_bearer_token);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }
            $response     = $http->post($apiUrl, $payload);
            $responseData = $response->json();
        } catch (\Throwable $e) {
            return ['enviado' => false, 'detalle' => 'Error al contactar la API de WhatsApp'];
        }

        WhatsappMessage::create([
            'response'     => json_encode($responseData),
            'recipient_id' => $recipient,
            'deleted'      => 0,
            'type'         => $tipoRegistro,
        ]);

        return [
            'enviado'  => !empty($responseData['messages'][0]['id']),
            'response' => $responseData,
        ];
    }
}
