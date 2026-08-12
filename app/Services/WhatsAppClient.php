<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\PendingRequest;
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

        if (!$this->configuracionBasicaCompleta($settings) || empty($templateName)) {
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

        try {
            $responseData = $this->clienteHttp($settings)->post($this->urlApi($settings), $payload)->json();
        } catch (\Throwable $e) {
            WhatsappMessage::create([
                'response'     => json_encode(['error' => $e->getMessage()]),
                'recipient_id' => $recipient,
                'deleted'      => 0,
                'type'         => $tipoRegistro,
            ]);

            return ['enviado' => false, 'detalle' => 'Error al contactar la API de WhatsApp: ' . $e->getMessage()];
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

    /**
     * Envía un mensaje de texto libre (autoreply del webhook). A diferencia
     * de enviarPlantilla(), no registra en whatsapp_messages y no retorna
     * nada — los errores se ignoran a propósito (Meta ya recibió el 200 de
     * confirmación del webhook, no tiene sentido reintentar).
     */
    public function enviarTexto(string $telefono, string $texto): void
    {
        $settings = Setting::first();

        if (!$this->configuracionBasicaCompleta($settings)) {
            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $telefono,
            'type'              => 'text',
            'text'              => ['body' => $texto],
        ];

        try {
            $this->clienteHttp($settings)->post($this->urlApi($settings), $payload);
        } catch (\Throwable) {
            // Silencioso — Meta ya recibió el 200, no reintentar.
        }
    }

    /**
     * Verifica los 3 campos de configuración comunes a cualquier envío
     * (versión de API, ID del número, token). Cada método público valida
     * además lo que le sea propio (ej. nombre de plantilla).
     */
    private function configuracionBasicaCompleta(?Setting $settings): bool
    {
        return $settings
            && !empty($settings->wa_api_version)
            && !empty($settings->wa_phone_number_id)
            && !empty($settings->wa_bearer_token);
    }

    private function urlApi(Setting $settings): string
    {
        return "https://graph.facebook.com/{$settings->wa_api_version}/{$settings->wa_phone_number_id}/messages";
    }

    private function clienteHttp(Setting $settings): PendingRequest
    {
        $http = Http::withToken($settings->wa_bearer_token);

        if (app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }
}
