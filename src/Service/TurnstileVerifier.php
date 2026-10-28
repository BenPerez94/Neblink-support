<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérifie auprès de Cloudflare le jeton du captcha Turnstile envoyé avec un formulaire.
 */
class TurnstileVerifier
{
    private const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger,
        #[Autowire('%env(TURNSTILE_SECRET_KEY)%')] private string $secretKey,
    ) {
    }

    public function verify(?string $token, ?string $ip = null): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $resultat = $this->client->request('POST', self::URL, [
                'body' => array_filter([
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $ip,
                ]),
                'timeout' => 5,
            ])->toArray();
        } catch (\Throwable $e) {
            // Cloudflare injoignable : on refuse par prudence, le visiteur peut réessayer
            $this->logger->error('Vérification Turnstile impossible : {erreur}', ['erreur' => $e->getMessage()]);

            return false;
        }

        if (!($resultat['success'] ?? false)) {
            $this->logger->info('Captcha Turnstile refusé : {codes}', ['codes' => implode(', ', $resultat['error-codes'] ?? [])]);

            return false;
        }

        return true;
    }
}
