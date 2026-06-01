<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.webhook_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request)
    {
        $body     = $request->all();
        $messages = $body['entry'][0]['changes'][0]['value']['messages'] ?? null;

        if (!$messages) {
            return response('OK', 200);
        }

        $message = $messages[0];

        // Solo responder a mensajes de texto para evitar loops con status/notificaciones
        if (($message['type'] ?? '') !== 'text') {
            return response('OK', 200);
        }

        $this->sendAutoReply($message['from']);

        return response('OK', 200);
    }

    private function sendAutoReply(string $phone): void
    {
        $setting = Setting::first();

        if (
            !$setting ||
            empty($setting->wa_api_version) ||
            empty($setting->wa_phone_number_id) ||
            empty($setting->wa_bearer_token)
        ) {
            return;
        }

        $texto = "Hola 👋 Gracias por comunicarte con Contacto Médico.\n\n"
               . "Esta línea es exclusiva para el envío de confirmaciones y documentos. "
               . "No cuenta con atención por este medio.\n\n"
               . "Para atención personalizada comunícate con tu sede más cercana 📞 "
               . "https://beacons.ai/contactomedicocolombia\n\n"
               . "¡Estamos para servirte! 🙂";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $texto],
        ];

        $apiUrl = "https://graph.facebook.com/{$setting->wa_api_version}/{$setting->wa_phone_number_id}/messages";

        try {
            $http = Http::withToken($setting->wa_bearer_token);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }
            $http->post($apiUrl, $payload);
        } catch (\Throwable) {
            // Silencioso — Meta ya recibió el 200, no reintentar
        }
    }
}
