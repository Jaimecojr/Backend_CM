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

    /**
     * Meta's one-time subscription handshake: echoes the challenge only for our verify token.
     */
    public function verify(Request $request)
    {
        $mode      = (string) $request->query('hub_mode');
        $token     = (string) $request->query('hub_verify_token');
        $challenge = (string) $request->query('hub_challenge');
        $expected  = (string) config('services.whatsapp.webhook_token');

        // An unset token must never match an empty one, and the challenge Meta sends is numeric:
        // anything else is refused instead of being echoed back (reflected content on our domain).
        if (
            $expected !== ''
            && $mode === 'subscribe'
            && hash_equals($expected, $token)
            && ctype_digit($challenge)
        ) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Auto-replies to inbound text messages; this number is send-only and has no live attention.
     *
     * SECURITY_REVIEW: the route is public by necessity, so the HMAC signature Meta computes with
     * the App Secret is verified before anything is read or sent.
     */
    public function handle(Request $request)
    {
        // Anyone can POST to this public URL; only payloads signed by Meta with the App Secret are
        // processed, otherwise a forged "from" would make us send messages through the company number.
        if (!$this->hasValidSignature($request)) {
            return response('Forbidden', 403);
        }

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

        if (!is_string($message['from'] ?? null)) {
            return response('OK', 200);
        }

        $this->sendAutoReply($message['from']);

        return response('OK', 200);
    }

    private function hasValidSignature(Request $request): bool
    {
        $secret = (string) config('services.whatsapp.app_secret');

        // Fail closed: without the secret there is no way to tell Meta from an attacker.
        if ($secret === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, (string) $request->header('X-Hub-Signature-256'));
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
