<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsappMessage;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    /**
     * Sends a template message (carnet, appointment notification) and
     * logs the result in whatsapp_messages. The language code is always
     * 'es_CO' — Meta rejects 'es' with error #132001.
     *
     * @param  string  $localPhone  LOCAL number without country prefix (e.g. "3001234567") — this method prepends '57' internally. Do not pass a number that already includes the prefix, or it will end up as "5757...".
     * @param  array<int, array<string, mixed>>  $components  Template components (Meta's header/body).
     * @return array{enviado: bool, response?: array, detalle?: string}
     */
    public function sendTemplate(string $localPhone, string $templateName, array $components, string $recordType): array
    {
        $settings = Setting::first();

        if (!$this->hasCompleteBasicConfiguration($settings) || empty($templateName)) {
            return ['enviado' => false, 'detalle' => 'Configuración de WhatsApp incompleta'];
        }

        $recipient = '57' . $localPhone;

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
            $responseData = $this->httpClient($settings)->post($this->urlApi($settings), $payload)->json();
        } catch (\Throwable $e) {
            WhatsappMessage::create([
                'response'     => json_encode(['error' => $e->getMessage()]),
                'recipient_id' => $recipient,
                'deleted'      => 0,
                'type'         => $recordType,
            ]);

            return ['enviado' => false, 'detalle' => 'Error al contactar la API de WhatsApp: ' . $e->getMessage()];
        }

        WhatsappMessage::create([
            'response'     => json_encode($responseData),
            'recipient_id' => $recipient,
            'deleted'      => 0,
            'type'         => $recordType,
        ]);

        return [
            'enviado'  => !empty($responseData['messages'][0]['id']),
            'response' => $responseData,
        ];
    }

    /**
     * Sends a free-text message (webhook autoreply). Unlike sendTemplate(),
     * it does not log to whatsapp_messages and returns nothing — errors
     * are ignored on purpose (Meta already received the webhook's 200
     * confirmation, so there's no point retrying).
     *
     * @param  string  $phoneWithPrefix  Number that ALREADY includes the country prefix (e.g. "573001234567") — comes as-is from Meta webhook's `from` field. Do not prepend '57' again.
     */
    public function sendText(string $phoneWithPrefix, string $text): void
    {
        $settings = Setting::first();

        if (!$this->hasCompleteBasicConfiguration($settings)) {
            return;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phoneWithPrefix,
            'type'              => 'text',
            'text'              => ['body' => $text],
        ];

        try {
            $this->httpClient($settings)->post($this->urlApi($settings), $payload);
        } catch (\Throwable) {
            // Silent — Meta already got the 200, no point retrying.
        }
    }

    /**
     * Returns the Setting if WhatsApp's basic configuration is complete
     * AND the given template field is also set; null if anything is missing.
     * Controllers use this instead of repeating their own check before
     * calling sendTemplate().
     */
    public function configurationForTemplate(string $templateField): ?Setting
    {
        $settings = Setting::first();

        if (!$this->hasCompleteBasicConfiguration($settings) || empty($settings->{$templateField})) {
            return null;
        }

        return $settings;
    }

    /**
     * Checks the 3 configuration fields common to any send (API version,
     * phone number ID, token). Each public method additionally validates
     * whatever is specific to it (e.g. template name).
     */
    private function hasCompleteBasicConfiguration(?Setting $settings): bool
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

    private function httpClient(Setting $settings): PendingRequest
    {
        $http = Http::withToken($settings->wa_bearer_token);

        if (app()->environment('local')) {
            $http = $http->withoutVerifying();
        }

        return $http;
    }
}
