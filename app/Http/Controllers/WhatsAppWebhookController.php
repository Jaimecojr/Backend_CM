<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WhatsAppClient;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __construct(private WhatsAppClient $whatsapp)
    {
    }

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
        $texto = "Hola 👋 Gracias por comunicarte con Contacto Médico.\n\n"
               . "Esta línea es exclusiva para el envío de confirmaciones y documentos. "
               . "No cuenta con atención por este medio.\n\n"
               . "Para atención personalizada comunícate con tu sede más cercana 📞 "
               . "https://beacons.ai/contactomedicocolombia\n\n"
               . "¡Estamos para servirte! 🙂";

        $this->whatsapp->sendText($phone, $texto);
    }
}
