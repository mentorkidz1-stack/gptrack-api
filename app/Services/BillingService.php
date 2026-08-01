<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Http;

/**
 * Paiement d'abonnement via FedaPay (agrégateur mobile money / carte
 * adapté au Bénin et à l'Afrique de l'Ouest), appelé en HTTP direct sur
 * l'API REST officielle plutôt qu'avec le SDK — endpoints et format de
 * signature de webhook vérifiés sur la documentation et le code source
 * officiels (docs.fedapay.com, github.com/fedapay/fedapay-php).
 *
 * Si aucune clé n'est configurée, isConfigured() est false : l'appelant
 * (BillingController) bascule alors sur un mode démo qui change le plan
 * directement sans paiement réel, pour ne pas bloquer le développement.
 */
class BillingService
{
    public function isConfigured(): bool
    {
        return filled(config('services.fedapay.secret_key'));
    }

    private function baseUrl(): string
    {
        return config('services.fedapay.environment') === 'live'
            ? 'https://api.fedapay.com/v1'
            : 'https://sandbox-api.fedapay.com/v1';
    }

    /**
     * Crée une transaction FedaPay puis génère son lien de paiement.
     * Retourne l'URL de paiement à rediriger côté client.
     */
    public function createCheckout(Company $company, string $plan, int $amountXof, string $callbackUrl): string
    {
        $client = Http::withToken(config('services.fedapay.secret_key'));

        $transaction = $client->post($this->baseUrl() . '/transactions', [
            'description' => "Abonnement GPTrack - Plan " . ucfirst($plan),
            'amount' => $amountXof,
            'currency' => ['iso' => 'XOF'],
            'callback_url' => $callbackUrl,
            'custom_metadata' => [
                'company_id' => $company->id,
                'plan' => $plan,
            ],
        ])->throw()->json();

        $token = $client->post($this->baseUrl() . '/transactions/' . $transaction['id'] . '/token')
            ->throw()
            ->json();

        return $token['url'];
    }

    /**
     * Vérifie la signature d'un webhook FedaPay.
     *
     * Format de l'en-tête X-FEDAPAY-SIGNATURE : "t=<timestamp>,s=<hmac>"
     * Signature = HMAC-SHA256("{timestamp}.{payload brut}", secret webhook)
     * (identique au schéma Stripe, repris tel quel par FedaPay).
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $header, int $tolerance = 300): bool
    {
        $secret = config('services.fedapay.webhook_secret');
        if (!$header || !$secret) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $item) {
            [$key, $value] = array_pad(explode('=', $item, 2), 2, null);
            if ($key === 't') {
                $timestamp = is_numeric($value) ? (int) $value : null;
            } elseif ($key === 's') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || empty($signatures)) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawPayload}", $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
