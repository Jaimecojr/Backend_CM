<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Verifies a Google reCAPTCHA token against Google's siteverify endpoint.
 *
 * The widget on the public website only proves something to the browser; without this server-side
 * check a bot can POST straight to the public endpoints and skip it entirely. Works for both v2
 * (no score in the response) and v3 (score compared against `services.recaptcha.min_score`).
 */
class RecaptchaVerifier
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /**
     * Verification is skipped when no secret is configured so local development and the test suite
     * work without Google credentials. Production must set RECAPTCHA_SECRET_KEY.
     */
    public function isEnabled(): bool
    {
        return filled(config('services.recaptcha.secret'));
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            // Same Windows/cURL local SSL workaround used by WhatsAppClient.
            $http = app()->environment('local') ? Http::withoutVerifying() : Http::withOptions([]);

            $response = $http->asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret'   => config('services.recaptcha.secret'),
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (\Throwable) {
            // Fail closed: if Google can't be reached the submission is rejected rather than let through.
            return false;
        }

        if (!$response->successful() || $response->json('success') !== true) {
            return false;
        }

        $score = $response->json('score');

        return $score === null || (float) $score >= (float) config('services.recaptcha.min_score', 0.5);
    }
}
