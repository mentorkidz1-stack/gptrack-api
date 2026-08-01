<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Comparaison faciale via AWS Rekognition (CompareFaces), appelée en HTTP
 * signé (Signature V4) plutôt qu'avec le SDK officiel : ce dernier est un
 * paquet Composer très volumineux qui échouait systématiquement à
 * l'installation sur cette machine (blocage antivirus + clone Git trop
 * lent). Guzzle est déjà une dépendance du projet.
 *
 * Si aucune clé AWS Rekognition n'est configurée dans .env, compare()
 * retombe sur une simulation (mode démo) clairement signalée par
 * `simulated => true`, pour ne pas casser le développement local.
 */
class FaceRecognitionService
{
    public function isConfigured(): bool
    {
        return filled(config('services.rekognition.key'))
            && filled(config('services.rekognition.secret'));
    }

    /**
     * Compare une photo de référence et un selfie (tous deux encodés en
     * base64) et retourne un score de similarité entre 0 et 100.
     *
     * @return array{score: float, simulated: bool, error: bool}
     */
    public function compare(?string $referencePhotoBase64, ?string $selfiePhotoBase64): array
    {
        if (!$this->isConfigured()) {
            return [
                'score' => rand(80, 99),
                'simulated' => true,
                'error' => false,
            ];
        }

        if (empty($referencePhotoBase64) || empty($selfiePhotoBase64)) {
            return ['score' => 0, 'simulated' => false, 'error' => true];
        }

        try {
            $response = $this->callCompareFaces($referencePhotoBase64, $selfiePhotoBase64);
            $matches = $response['FaceMatches'] ?? [];

            if (empty($matches)) {
                // Aucun visage correspondant trouvé (ou aucun visage détecté).
                return ['score' => 0, 'simulated' => false, 'error' => false];
            }

            $best = collect($matches)->sortByDesc('Similarity')->first();

            return [
                'score' => round((float) $best['Similarity'], 2),
                'simulated' => false,
                'error' => false,
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['score' => 0, 'simulated' => false, 'error' => true];
        }
    }

    /**
     * Appelle RekognitionService.CompareFaces via l'API JSON brute d'AWS,
     * signée en Signature Version 4.
     *
     * @throws GuzzleException
     */
    private function callCompareFaces(string $sourceBase64, string $targetBase64): array
    {
        $region = config('services.rekognition.region', 'eu-west-1');
        $accessKey = config('services.rekognition.key');
        $secretKey = config('services.rekognition.secret');

        $host = "rekognition.$region.amazonaws.com";
        $target = 'RekognitionService.CompareFaces';
        $contentType = 'application/x-amz-json-1.1';

        $payload = json_encode([
            // L'API JSON brute d'AWS attend les images en base64 : nos
            // colonnes sont déjà stockées ainsi, pas de décodage nécessaire.
            'SourceImage' => ['Bytes' => $sourceBase64],
            'TargetImage' => ['Bytes' => $targetBase64],
            'SimilarityThreshold' => 1,
        ], JSON_THROW_ON_ERROR);

        $headers = $this->signedHeaders(
            region: $region,
            host: $host,
            target: $target,
            contentType: $contentType,
            payload: $payload,
            accessKey: $accessKey,
            secretKey: $secretKey,
        );

        $client = new Client(['timeout' => 15]);
        $response = $client->post("https://$host/", [
            'headers' => $headers,
            'body' => $payload,
        ]);

        return json_decode((string) $response->getBody(), true) ?? [];
    }

    /**
     * Construit les en-têtes HTTP signés AWS Signature V4 pour une requête
     * JSON POST sans paramètres de requête (cas de toutes les API JSON AWS).
     *
     * @return array<string, string>
     */
    private function signedHeaders(
        string $region,
        string $host,
        string $target,
        string $contentType,
        string $payload,
        string $accessKey,
        string $secretKey,
    ): array {
        $service = 'rekognition';
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        $canonicalHeaders =
            "content-type:$contentType\n" .
            "host:$host\n" .
            "x-amz-date:$amzDate\n" .
            "x-amz-target:$target\n";
        $signedHeadersList = 'content-type;host;x-amz-date;x-amz-target';
        $payloadHash = hash('sha256', $payload);

        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeadersList,
            $payloadHash,
        ]);

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "$dateStamp/$region/$service/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->signingKey($secretKey, $dateStamp, $region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = "$algorithm Credential=$accessKey/$credentialScope, "
            . "SignedHeaders=$signedHeadersList, Signature=$signature";

        return [
            'Content-Type' => $contentType,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Target' => $target,
            'Authorization' => $authorization,
        ];
    }

    private function signingKey(string $secretKey, string $dateStamp, string $region, string $service): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
