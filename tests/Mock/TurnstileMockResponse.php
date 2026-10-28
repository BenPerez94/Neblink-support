<?php

namespace App\Tests\Mock;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Remplace l'appel à Cloudflare Turnstile pendant les tests :
 * le jeton « jeton-valide » est accepté, tous les autres sont refusés.
 */
final class TurnstileMockResponse
{
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        parse_str((string) ($options['body'] ?? ''), $donnees);
        $valide = ($donnees['response'] ?? '') === 'jeton-valide';

        return new MockResponse((string) json_encode([
            'success' => $valide,
            'error-codes' => $valide ? [] : ['invalid-input-response'],
        ]), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
