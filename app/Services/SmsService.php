<?php

namespace App\Services;

use Twilio\Rest\Client;

/**
 * Envoi de SMS réel via Twilio.
 *
 * Si aucun identifiant Twilio n'est configuré dans .env, send() renvoie
 * simplement false : l'appelant garde alors le comportement de dev
 * (OTP affiché dans la réponse JSON / la console).
 */
class SmsService
{
    public function isConfigured(): bool
    {
        return filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config('services.twilio.from'));
    }

    public function send(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $client = new Client(
                config('services.twilio.sid'),
                config('services.twilio.token')
            );

            $client->messages->create($this->toE164($phone), [
                'from' => config('services.twilio.from'),
                'body' => $message,
            ]);

            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Normalise un numéro local (ex: "97000000") au format E.164
     * requis par Twilio, en utilisant l'indicatif pays par défaut.
     */
    public static function toE164(string $phone): string
    {
        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        $countryCode = config('services.twilio.default_country_code', '+229');

        return $countryCode . ltrim($phone, '0');
    }
}
